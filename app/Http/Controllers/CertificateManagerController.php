<?php

namespace App\Http\Controllers;

use App\Models\Firewall;
use App\Services\PfSenseApiService;
use Illuminate\Http\Request;

class CertificateManagerController extends Controller
{
    public function index(Firewall $firewall, Request $request)
    {
        $api = new PfSenseApiService($firewall);
        $tab = $request->query('tab', 'cas');
        $data = [];
        $error = null;

        try {
            if ($tab === 'cas') {
                $response = $api->getCertificateAuthorities();
                $data['cas'] = $response['data'] ?? [];

                // DEBUG: log the refid of each CA so we can compare with pfSense web GUI
                \Log::info('CA list from pfSense', [
                    'firewall' => $firewall->id,
                    'cas' => array_map(fn($ca) => [
                        'id'    => $ca['id']    ?? null,
                        'refid' => $ca['refid'] ?? null,
                        'descr' => $ca['descr'] ?? null,
                    ], $data['cas']),
                ]);


            } elseif ($tab === 'certificates') {
                // Fetch CAs first to build a refid → description lookup
                $caData = [];
                try {
                    $caResponse = $api->getCertificateAuthorities();
                    foreach ($caResponse['data'] ?? [] as $ca) {
                        $refid = $ca['refid'] ?? $ca['uuid'] ?? null;
                        if ($refid) {
                            $caData[$refid] = $ca['descr'] ?? $refid;
                        }
                    }
                } catch (\Throwable) {
                    // CA lookup is best-effort; proceed without it
                }

                $response  = $api->getCertificates();
                $certs     = $response['data'] ?? [];

                // pfSense: scan service configs to build cert usage map
                // OPNsense returns in_use natively; getCertInUseMap() returns null for OPNsense
                $pfSenseUsageMap = $api->getCertInUseMap();

                foreach ($certs as &$cert) {
                    // Resolve caref to CA description (pfSense fallback; OPNsense sets issuer_name already)
                    if (!isset($cert['issuer_name']) || $cert['issuer_name'] === null) {
                        $caref = $cert['caref'] ?? null;
                        $cert['issuer_name'] = $caref ? ($caData[$caref] ?? null) : null;
                    }

                    $refid = $cert['refid'] ?? '';

                    if ($pfSenseUsageMap !== null) {
                        // pfSense: use the scanned service map
                        $services = $pfSenseUsageMap[$refid] ?? [];
                        $cert['in_use']          = count($services) > 0;
                        $cert['in_use_services'] = $services;
                    } else {
                        // OPNsense (or scan failed): normalize whatever the API returned
                        $cert['in_use_services'] = [];
                        if (!isset($cert['in_use']) || $cert['in_use'] === null) {
                            $refcount = $cert['refcount'] ?? $cert['certcount'] ?? $cert['is_in_use'] ?? null;
                            $cert['in_use'] = $refcount !== null ? ((int)$refcount > 0) : null;
                        } elseif (is_string($cert['in_use'])) {
                            $cert['in_use'] = (bool)(int)$cert['in_use'];
                        }
                    }
                }
                unset($cert);

                $data['certificates'] = $certs;

            } elseif ($tab === 'crls') {
                $response = $api->getCRLs();
                $data['crls'] = $response['data'] ?? [];
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        return view('system.certificate_manager.index', compact('firewall', 'tab', 'data', 'error'));
    }

    public function createCa(Firewall $firewall)
    {
        return view('system.certificate_manager.cas.create', compact('firewall'));
    }

    public function storeCa(Firewall $firewall, Request $request)
    {
        $api = new PfSenseApiService($firewall);
        // CA generation (especially 4096-bit RSA) can take 60-120s on firewall hardware.
        // Increase timeout so key generation doesn't get killed mid-operation.
        $api->setApiTimeout(120);

        $method = $request->input('method', 'internal');

        try {
            if ($method === 'internal') {
                // Only pass fields relevant to CA generation.
                // x-show hides import fields but does NOT prevent them from being submitted;
                // sending empty crt/prv to pfSense's generate endpoint causes an internal failure.
                // dn_commonname is required by OpenSSL; fall back to descr if the user left it blank.
                $descr = $request->input('descr', '');
                $data = array_filter([
                    'descr'                 => $descr,
                    'trust'                 => (bool) $request->input('trust', 0),
                    'randomserial'          => (bool) $request->input('randomserial', 0),
                    'is_intermediate'       => false,
                    'keytype'               => $request->input('keytype', 'RSA'),
                    'keylen'                => (int) $request->input('keylen', 2048),
                    'digest_alg'            => $request->input('digest_alg', 'sha256'),
                    'lifetime'              => (int) $request->input('lifetime', 3650),
                    'dn_country'            => $request->input('dn_country'),
                    'dn_state'              => $request->input('dn_state'),
                    'dn_city'               => $request->input('dn_city'),
                    'dn_organization'       => $request->input('dn_organization'),
                    'dn_organizationalunit' => $request->input('dn_ou'),
                    'dn_email'              => $request->input('dn_email'),
                    'dn_commonname'         => $request->input('dn_commonname') ?: $descr,
                ], fn($v) => $v !== null && $v !== '');

                $api->generateCertificateAuthority($data);
            } else {
                $data = array_filter([
                    'descr' => $request->input('descr'),
                    'crt'   => $request->input('crt'),
                    'prv'   => $request->input('prv'),
                ], fn($v) => $v !== null && $v !== '');

                $api->createCertificateAuthority($data);
            }

            return redirect()->route('system.certificate_manager.index', ['firewall' => $firewall, 'tab' => 'cas'])
                ->with('success', 'Certificate Authority created successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to create CA: ' . $e->getMessage())->withInput();
        }
    }

    public function destroyCa(Firewall $firewall, string $id)
    {
        $api = new PfSenseApiService($firewall);
        try {
            \Log::info('destroyCa: deleting CA', ['firewall' => $firewall->id, 'refid' => $id]);
            $api->deleteCertificateAuthority($id);
            return redirect()->route('system.certificate_manager.index', ['firewall' => $firewall, 'tab' => 'cas'])
                ->with('success', 'Certificate Authority deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete CA: ' . $e->getMessage());
        }
    }

    public function createCert(Firewall $firewall)
    {
        $api = new PfSenseApiService($firewall);
        $cas = [];
        try {
            $response = $api->getCertificateAuthorities();
            $cas = $response['data'] ?? [];
        } catch (\Exception $e) {
            // Ignore error, just empty CAs
        }
        return view('system.certificate_manager.certificates.create', compact('firewall', 'cas'));
    }

    public function storeCert(Firewall $firewall, Request $request)
    {
        $api    = new PfSenseApiService($firewall);
        $method = $request->input('method', 'internal');

        try {
            if ($method === 'internal') {
                // Only pass fields relevant to cert generation — not empty import fields
                // dn_commonname is required by OpenSSL; fall back to descr if blank.
                $descr = $request->input('descr', '');
                $data = array_filter([
                    'descr'           => $descr,
                    'caref'           => $request->input('caref'),
                    'keytype'         => $request->input('keytype', 'RSA'),
                    'keylen'          => (int) $request->input('keylen', 2048),
                    'digest_alg'      => $request->input('digest_alg', 'sha256'),
                    'lifetime'        => (int) $request->input('lifetime', 398),
                    'dn_country'      => $request->input('dn_country'),
                    'dn_state'        => $request->input('dn_state'),
                    'dn_city'         => $request->input('dn_city'),
                    'dn_organization' => $request->input('dn_organization'),
                    'dn_email'        => $request->input('dn_email'),
                    'dn_commonname'   => $request->input('dn_commonname') ?: $descr,
                ], fn($v) => $v !== null && $v !== '');

                $api->generateCertificate($data);
            } else {
                $data = array_filter([
                    'descr' => $request->input('descr'),
                    'crt'   => $request->input('crt'),
                    'prv'   => $request->input('prv'),
                ], fn($v) => $v !== null && $v !== '');

                $api->createCertificate($data);
            }

            return redirect()->route('system.certificate_manager.index', ['firewall' => $firewall, 'tab' => 'certificates'])
                ->with('success', 'Certificate created successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to create Certificate: ' . $e->getMessage())->withInput();
        }
    }

    public function destroyCert(Firewall $firewall, string $id)
    {
        $api = new PfSenseApiService($firewall);
        try {
            $api->deleteCertificate($id);
            return redirect()->route('system.certificate_manager.index', ['firewall' => $firewall, 'tab' => 'certificates'])
                ->with('success', 'Certificate deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete Certificate: ' . $e->getMessage());
        }
    }
    public function createCrl(Firewall $firewall)
    {
        $api = new PfSenseApiService($firewall);
        $cas = [];
        try {
            $response = $api->getCertificateAuthorities();
            $cas = $response['data'] ?? [];
        } catch (\Exception $e) {
            // Ignore error
        }
        return view('system.certificate_manager.crls.create', compact('firewall', 'cas'));
    }

    public function storeCrl(Firewall $firewall, Request $request)
    {
        $api = new PfSenseApiService($firewall);
        $method = $request->input('method');
        $data = $request->except(['_token', 'method']);

        try {
            // Standardize logic: 'internal' usually means creating a new internal list, 
            // but for CRLs it might just be 'create'. 
            // The API payload structure depends on the endpoint requirements.
            // For now, passing data through.
            $api->createCRL($data);

            return redirect()->route('system.certificate_manager.index', ['firewall' => $firewall, 'tab' => 'crls'])
                ->with('success', 'CRL created successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to create CRL: ' . $e->getMessage())->withInput();
        }
    }

    public function destroyCrl(Firewall $firewall, string $id)
    {
        $api = new PfSenseApiService($firewall);
        try {
            $api->deleteCRL($id);
            return redirect()->route('system.certificate_manager.index', ['firewall' => $firewall, 'tab' => 'crls'])
                ->with('success', 'CRL deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete CRL: ' . $e->getMessage());
        }
    }
}
