<?php
/**
 * Dc_Minifier
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Dc
 * @package    Dc_Minifier
 * @copyright  Copyright (c) 2014 Damián Culotta. (http://www.damianculotta.com.ar/)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Dc_Minifier_Helper_Data extends Mage_Core_Helper_Data
{
    
    private function _compressCss($buffer) {
        if (Mage::app()->getStore()->getConfig('minifier/settings/css')) {
            $buffer = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $buffer);
            $buffer = str_replace(array("\r\n","\r","\n","\t",'  ','    ','     '), '', $buffer);
            $buffer = preg_replace(array('(( )+{)','({( )+)'), '{', $buffer);
            $buffer = preg_replace(array('(( )+})','(}( )+)','(;( )*})'), '}', $buffer);
            $buffer = preg_replace(array('(;( )+)','(( )+;)'), ';', $buffer);
        }
        return $buffer;
    }
    
    private function _compressJs($buffer) {
        if (Mage::app()->getStore()->getConfig('minifier/settings/js')) {
            $jsmin = new Dc_Minifier_JSMin($buffer);
            $buffer = $jsmin->min(); 
        }
        return $buffer;
    }
    
    /**
     * Merge specified files into one
     *
     * By default will not merge, if there is already merged file exists and it
     * was modified after its components
     * If target file is specified, will attempt to write merged contents into it,
     * otherwise will return merged content
     * May apply callback to each file contents. Callback gets parameters:
     * (<existing system filename>, <file contents>)
     * May filter files by specified extension(s)
     * Returns false on error
     *
     * @param array $srcFiles
     * @param string|false $targetFile - file path to be written
     * @param bool $mustMerge
     * @param callback $beforeMergeCallback
     * @param array|string $extensionsFilter
     * @return bool|string
     */
    public function mergeFiles(array $srcFiles, $targetFile = false, $mustMerge = false,
        $beforeMergeCallback = null, $extensionsFilter = array())
    {
        try {
            // check whether merger is required
            $shouldMerge = $mustMerge || !$targetFile;
            if (!$shouldMerge) {
                if (!file_exists($targetFile)) {
                    $shouldMerge = true;
                } else {
                    $targetMtime = filemtime($targetFile);
                    foreach ($srcFiles as $file) {
                        if (!file_exists($file) || @filemtime($file) > $targetMtime) {
                            $shouldMerge = true;
                            break;
                        }
                    }
                }
            }

            // merge contents into the file
            if ($shouldMerge) {
                if ($targetFile && !is_writeable(dirname($targetFile))) {
                    // no translation intentionally
                    throw new Exception(sprintf('Path %s is not writeable.', dirname($targetFile)));
                }

                // filter by extensions
                if ($extensionsFilter) {
                    if (!is_array($extensionsFilter)) {
                        $extensionsFilter = array($extensionsFilter);
                    }
                    if (!empty($srcFiles)){
                        foreach ($srcFiles as $key => $file) {
                            $fileExt = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                            if (!in_array($fileExt, $extensionsFilter)) {
                                unset($srcFiles[$key]);
                            }
                        }
                    }
                }
                if (empty($srcFiles)) {
                    // no translation intentionally
                    throw new Exception('No files to compile.');
                }

                $data = '';
                foreach ($srcFiles as $file) {
                    if (!file_exists($file)) {
                        continue;
                    }
                    switch(strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
                        case 'css':
                            $contents = $this->_compressCss(file_get_contents($file)) . "\n";
                            break;
                        case 'js':
                            $contents = $this->_compressJs(file_get_contents($file)) . "\n";
                            break;
                        default:
                            $contents = file_get_contents($file) . "\n";
                    }
                    if ($beforeMergeCallback && is_callable($beforeMergeCallback)) {
                        $contents = call_user_func($beforeMergeCallback, $file, $contents);
                    }
                    $data .= $contents;
                }
                if (!$data) {
                    // no translation intentionally
                    throw new Exception(sprintf("No content found in files:\n%s", implode("\n", $srcFiles)));
                }
                if ($targetFile) {
                    file_put_contents($targetFile, $data, LOCK_EX);
                } else {
                    return $data; // no need to write to file, just return data
                }
            }

            return true; // no need in merger or merged into file successfully
        } catch (Exception $e) {
            Mage::logException($e);
        }
        return false;
    }
    
}