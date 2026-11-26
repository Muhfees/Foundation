# Read-only image sampling. Writes only the resulting report under docs/.
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing
$projectRoot = Split-Path $PSScriptRoot -Parent
$referenceRoot = Join-Path $projectRoot 'Reference'
$report = foreach ($file in (Get-ChildItem -LiteralPath $referenceRoot -Filter '*.png' | Sort-Object { [int]($_.Name.Split('.')[0]) })) {
    $bitmap = [System.Drawing.Bitmap]::new($file.FullName)
    try {
        $counts = @{}
        for ($y = 0; $y -lt $bitmap.Height; $y += 8) {
            for ($x = 0; $x -lt $bitmap.Width; $x += 8) {
                $pixel = $bitmap.GetPixel($x, $y)
                $hex = '#{0:X2}{1:X2}{2:X2}' -f $pixel.R, $pixel.G, $pixel.B
                if ($counts.ContainsKey($hex)) { $counts[$hex]++ } else { $counts[$hex] = 1 }
            }
        }
        [pscustomobject]@{
            file = $file.Name
            width = $bitmap.Width
            height = $bitmap.Height
            sample_stride_px = 8
            most_common = @($counts.GetEnumerator() | Sort-Object Value -Descending | Select-Object -First 8 | ForEach-Object {
                [pscustomobject]@{ hex = $_.Key; samples = $_.Value }
            })
        }
    } finally { $bitmap.Dispose() }
}
$output = Join-Path $projectRoot 'docs/reference-colours.json'
[System.IO.Directory]::CreateDirectory((Split-Path $output -Parent)) | Out-Null
$report | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $output -Encoding UTF8
$report | ForEach-Object { '{0}: {1}x{2}; {3}' -f $_.file.Split('.')[0], $_.width, $_.height, (($_.most_common | Select-Object -First 5 | ForEach-Object { $_.hex }) -join ', ') }
