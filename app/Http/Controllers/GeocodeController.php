<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GeocodeController extends Controller
{
    /**
     * Get autocomplete suggestions.
     */
    public function suggest(Request $request)
    {
        $query = $request->input('q');

        if (empty($query) || strlen($query) < 3) {
            return response()->json(['suggestions' => []]);
        }

        try {
            $response = Http::get('https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/suggest', [
                'f' => 'json',
                'text' => $query,
                'maxSuggestions' => 5,
            ]);

            return $response->json();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Geocoding service unavailable', 'suggestions' => []], 500);
        }
    }

    /**
     * Retrieve details for a specific suggestion using its magicKey.
     */
    public function retrieve(Request $request)
    {
        $magicKey = $request->input('magicKey');

        if (empty($magicKey)) {
            return response()->json(['error' => 'Magic Key required'], 400);
        }

        try {
            $response = Http::get('https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/findAddressCandidates', [
                'f' => 'json',
                'magicKey' => $magicKey,
                'maxLocations' => 1,
            ]);

            $data = $response->json();

            if (!empty($data['candidates'][0])) {
                return response()->json($data['candidates'][0]);
            }

            return response()->json(['error' => 'Location not found'], 404);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Geocoding service unavailable'], 500);
        }
    }
}
