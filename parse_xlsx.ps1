Add-Type -AssemblyName System.IO.Compression.FileSystem

$file = Get-ChildItem "C:\xampp\htdocs\cms\.claude\worktrees\optimistic-neumann\DB_demo\Import*" | Select-Object -First 1

$zip = [System.IO.Compression.ZipFile]::OpenRead($file.FullName)

$ssEntry = $zip.Entries | Where-Object { $_.Name -eq "sharedStrings.xml" }
$ssStream = $ssEntry.Open()
$ssReader = New-Object System.IO.StreamReader($ssStream, [System.Text.Encoding]::UTF8)
$ssContent = $ssReader.ReadToEnd()
$ssReader.Close()
$ssStream.Close()

$xml = [xml]$ssContent
$ns = @{ns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"}
$strings = @()

foreach ($si in $xml.SelectNodes("//ns:si", $ns)) {
    $t = $si.SelectSingleNode("ns:t", $ns)
    if ($t) {
        $strings += $t.InnerText
    } else {
        $text = ""
        foreach ($r in $si.SelectNodes("ns:r", $ns)) {
            $rt = $r.SelectSingleNode("ns:t", $ns)
            if ($rt) { $text += $rt.InnerText }
        }
        $strings += $text
    }
}

$zip.Dispose()

$zip2 = [System.IO.Compression.ZipFile]::OpenRead($file.FullName)
$sheetEntry = $zip2.Entries | Where-Object { $_.Name -eq "sheet1.xml" }
$shStream = $sheetEntry.Open()
$shReader = New-Object System.IO.StreamReader($shStream, [System.Text.Encoding]::UTF8)
$shContent = $shReader.ReadToEnd()
$shReader.Close()
$shStream.Close()
$zip2.Dispose()

$sx = [xml]$shContent
foreach ($row in $sx.SelectNodes("//ns:row", $ns)) {
    $cells = @()
    foreach ($c in $row.SelectNodes("ns:c", $ns)) {
        $t = $c.GetAttribute("t")
        $v = $c.SelectSingleNode("ns:v", $ns)
        if ($t -eq "s" -and $v) {
            $idx = [int]$v.InnerText
            $cells += $strings[$idx]
        } elseif ($v) {
            $cells += $v.InnerText
        } else {
            $cells += ""
        }
    }
    $cells -join "|"
}
