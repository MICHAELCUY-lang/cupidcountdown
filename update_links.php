<?php
/**
 * Link Updater Script
 * 
 * This script helps automate the process of updating links in your PHP files
 * from file.php to file (without extension)
 */

// Configuration
$directory = __DIR__; // Current directory, change if needed
$dryRun = true;       // Set to false to apply changes, true to just see what would change
$backupFiles = true;  // Make backup copies of files before changing them

// Files to skip
$skipFiles = [
    'update_links.php', // Skip this script
    '.', 
    '..'
];

// Patterns to look for and their replacements
$patterns = [
    // Links in href attributes - careful to not match things like images
    '~href=[\'"]([^\'"/]+)\.php([\'"\?])~' => 'href="$1$2',
    
    // Form actions
    '~action=[\'"]([^\'"/]+)\.php([\'"\?])~' => 'action="$1$2',
    
    // PHP redirects
    '~header\([\'"]Location:\s*([^\'"/]+)\.php~' => 'header(\'Location: $1',
    
    // Redirect function calls
    '~redirect\([\'"]([^\'"/]+)\.php~' => 'redirect(\'$1',
];

// Counter for statistics
$stats = [
    'files_processed' => 0,
    'files_modified' => 0,
    'replacements' => 0,
];

// Create backup directory if needed
$backupDir = $directory . '/link_updater_backups';
if ($backupFiles && !file_exists($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Process files in directory
function processDirectory($dir) {
    global $skipFiles, $patterns, $dryRun, $backupFiles, $backupDir, $stats;
    
    $files = scandir($dir);
    
    foreach ($files as $file) {
        // Skip directories and specified files
        if (in_array($file, $skipFiles) || is_dir($dir . '/' . $file)) {
            continue;
        }
        
        // Only process PHP files
        if (!preg_match('/\.php$/', $file)) {
            continue;
        }
        
        $filePath = $dir . '/' . $file;
        $stats['files_processed']++;
        
        echo "Processing file: $filePath\n";
        
        // Read file content
        $content = file_get_contents($filePath);
        $newContent = $content;
        
        // Apply all patterns
        foreach ($patterns as $pattern => $replacement) {
            $count = 0;
            $newContent = preg_replace($pattern, $replacement, $newContent, -1, $count);
            $stats['replacements'] += $count;
            
            if ($count > 0) {
                echo "  - Found $count matches for pattern: $pattern\n";
            }
        }
        
        // If changes were made and not in dry run mode, write file
        if ($content !== $newContent) {
            $stats['files_modified']++;
            
            if (!$dryRun) {
                // Create backup if enabled
                if ($backupFiles) {
                    $backupPath = $backupDir . '/' . $file . '.bak';
                    file_put_contents($backupPath, $content);
                    echo "  - Created backup: $backupPath\n";
                }
                
                // Write changes
                file_put_contents($filePath, $newContent);
                echo "  - Updated file: $filePath\n";
            } else {
                echo "  - Would update file (dry run): $filePath\n";
            }
        } else {
            echo "  - No changes needed\n";
        }
    }
}

// Start processing
echo "Starting link update script...\n";
echo "Dry run mode: " . ($dryRun ? 'ON' : 'OFF') . "\n";
echo "Backup files: " . ($backupFiles ? 'ON' : 'OFF') . "\n\n";

processDirectory($directory);

// Display statistics
echo "\nSummary:\n";
echo "Files processed: {$stats['files_processed']}\n";
echo "Files with changes: {$stats['files_modified']}\n";
echo "Total replacements: {$stats['replacements']}\n";

if ($dryRun) {
    echo "\nThis was a dry run. Set \$dryRun to false to apply changes.\n";
}