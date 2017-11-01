<?php

/**
 * @Author: anhproduction
 * @Date:   2017-10-27 16:22:09
 * @Last Modified by:   anhproduction
 * @Last Modified time: 2017-11-01 13:23:25
 */
namespace AnhNDN\MageCustomErrorPages;

use ErrorException;
use Composer\Composer;
use Composer\Script\Event;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\Installer\InstallerEvent;
use Composer\Package\PackageInterface;
use Composer\Installer\InstallerEvents;
use Composer\Installer\LibraryInstaller;
use Composer\Installer\InstallerInterface;
use Composer\EventDispatcher\EventSubscriberInterface;

class Installer
{
    /**
     * @var rootDir
     */
    protected $rootDir;

    /**
     * @var exclude
     */
    protected $exclude = ['.backup-errors', '.htaccess'];

    /**
     * @var Filesystem
     */
    protected $fileSystem;

    /**
     * @var gitIgnore
     */
    protected $gitIgnore;

    /**
     * @var hasChangeIgnore
     */
    protected $hasChangeIgnore = false;

    /**
     * @var ignoreLines
     */
    protected $ignoreLines = array();

    /**
     * @param Filesystem $fileSystem
     * @param string $rootDir
     */
    public function __construct(Filesystem $fileSystem, $rootDir)
    {
        $this->fileSystem = $fileSystem;
        $this->rootDir    = $rootDir;
        $this->gitIgnore  = sprintf("%s/.gitignore", $rootDir);
        $this->getGitIgnore();
    }

    /**
     * @return none
     */
    public function install()
    {
        $destination = $this->getMagentoErrorDir();
        $source      = $this->getMagentoCustomErrorDir();
        $backup      = $this->getMagentoCustomErrorBackupDir();

        if (!file_exists($backup)) {
            mkdir($backup);
            $iteratorBackup = $this->getIterator($destination, RecursiveIteratorIterator::SELF_FIRST);
            foreach ($iteratorBackup as $item) {

                $destinationFile = sprintf("%s/%s", $backup, $iteratorBackup->getSubPathName());
                if ($item->isDir()) {
                    $fileExists = file_exists($destinationFile);
                    if (!$fileExists && is_link($destinationFile)) {
                        throw new \RuntimeException(
                            sprintf(
                                'File: "%s" appears to be a broken symlink referencing: "%s"',
                                $destinationFile,
                                readlink($destinationFile)
                            )
                        );
                    }

                    if (!$fileExists) {
                        mkdir($destinationFile);
                    }
                    continue;
                }

                copy($item, $destinationFile);
            }
        }
        
        $iterator = $this->getIterator($source, RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) {

            $destinationFile = sprintf("%s/%s", $destination, $iterator->getSubPathName());

            if ($this->exclude($iterator->getSubPathName())) {
                continue;
            }

            if (!file_exists($destinationFile)) {
                continue;
            }

            if ($item->isDir()) {
                $fileExists = file_exists($destinationFile);
                if (!$fileExists && is_link($destinationFile)) {
                    throw new \RuntimeException(
                        sprintf(
                            'File: "%s" appears to be a broken symlink referencing: "%s"',
                            $destinationFile,
                            readlink($destinationFile)
                        )
                    );
                }

                if (!$fileExists) {
                   mkdir($destinationFile);
                }
                continue;
            }

            copy($item, $destinationFile);
        }

        try {
            $this->addGitIgnore(ltrim($backup, $this->rootDir));
            $this->saveGitIgnore();
            file_put_contents($source . DIRECTORY_SEPARATOR . '.htaccess', "Deny from all");
        } catch(Exception $e) {
            #code
        }
    }

    /**
     * @return none
     */
    public function unInstall()
    {
        $destination = $this->getMagentoErrorDir();
        $backup      = $this->getMagentoCustomErrorBackupDir();

        $iterator = $this->getIterator($backup, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $destinationFile = sprintf("%s/%s", $destination, $iterator->getSubPathName());

            if ($this->exclude($iterator->getSubPathName())) {
                continue;
            }

            if (!file_exists($destinationFile)) {
                continue;
            }

            if ($item->isDir()) {
                $fileExists = file_exists($destinationFile);
                if (!$fileExists && is_link($destinationFile)) {
                    throw new \RuntimeException(
                        sprintf(
                            'File: "%s" appears to be a broken symlink referencing: "%s"',
                            $destinationFile,
                            readlink($destinationFile)
                        )
                    );
                }

                if (!$fileExists) {
                   mkdir($destinationFile);
                }
                continue;
            }

            copy($item, $destinationFile);
        }

        $this->fileSystem->removeDirectory($backup);
        $this->removeGitIgnore(ltrim($backup, $this->rootDir));
        $this->saveGitIgnore();
    }

    /**
     * @param string $source
     * @param int $flags
     * @return RecursiveIteratorIterator
     */
    public function getIterator($source, $flags)
    {
        return new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $source,
                RecursiveDirectoryIterator::SKIP_DOTS
            ),
            $flags
        );
    }

    /**
     * @return string
     */
    public function getMagentoErrorDir()
    {
        return $this->rootDir . DIRECTORY_SEPARATOR . 'errors';
    }

    /**
     * @return string
     */
    public function getMagentoCustomErrorDir()
    {
        return $this->rootDir . DIRECTORY_SEPARATOR . '.custom-errors';
    }
    
    /**
     * @return string
     */
    public function getMagentoCustomErrorBackupDir()
    {
        return $this->getMagentoCustomErrorDir() . DIRECTORY_SEPARATOR . '.backup-errors';
    }

    /**
     * @param  string $filePath
     * @return boolean
     */
    public function exclude($filePath)
    {
        foreach ($this->exclude as $exclude) {
            if (substr($filePath, 0, strlen($exclude)) === $exclude) {
                return true;
            } elseif ($exclude === $filePath) {
               return true;
            }
        }
        return false;
    }

    /**
     * @return none
     */
    public function getGitIgnore()
    {
        if (file_exists($this->gitIgnore)) {
            $this->ignoreLines = explode("\n", file_get_contents($this->gitIgnore));
        }
    }

    /**
     * @param string $path
     */
    public function addGitIgnore($path)
    {
        if (!file_exists($this->gitIgnore)) return false;
        if (!in_array($path, $this->ignoreLines)) {
            $this->ignoreLines[]   = $path;
            $this->hasChangeIgnore = true;
        }
    }

    /**
     * @param string $path
     */
    public function removeGitIgnore($path)
    {
        if (!file_exists($this->gitIgnore)) return false;

        $index = array_search($path, $this->ignoreLines);
        if ($index !== false) {
            unset($this->ignoreLines[$index]);
            $this->hasChangeIgnore = true;
        }
    }

    /**
     * @return boolean
     */
    public function saveGitIgnore()
    {
        if (!file_exists($this->gitIgnore)) return false;
        if ($this->hasChangeIgnore) {
            file_put_contents($this->gitIgnore, implode("\n", $this->ignoreLines));
            return true;
        }
        return false;
    }
}
