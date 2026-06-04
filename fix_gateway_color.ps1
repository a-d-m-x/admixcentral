$file = "resources/views/dashboard.blade.php"
$lines = Get-Content $file
$out = $lines | Where-Object { $_ -notmatch 'bg-green-100 text-green-700 dark:bg-green-900' }
Set-Content $file $out
Write-Host "Done. Lines before: $($lines.Count), after: $($out.Count)"
