<?php

/**
 * @Author: anhproduction
 * @Date:   2017-10-27 16:22:09
 * @Last Modified by:   anhproduction
 * @Last Modified time: 2017-10-29 18:26:57
 */
namespace AnhNDN\MageCustomErrorPages;

use Composer\Composer;
use Composer\Script\Event;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use Composer\Plugin\PluginInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\Installer\InstallerEvent;
use Composer\Package\PackageInterface;
use Composer\Installer\InstallerEvents;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\DependencyResolver\Operation\OperationInterface;

class Plugin implements PluginInterface, EventSubscriberInterface
{

    /**
     * @var Composer
     */
    protected $composer;

    /**
     * @var IOInterface
     */
    protected $io;

    /**
     * Vendor Directory
     *
     * @var string
     */
    protected $vendorDir;

    /**
     * Package Extra
     *
     * @var array
     */
    protected $packageExtra;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * Output Prefix
     *
     * @var string
     */
    protected $ioPrefix = '  - <comment>CustomErrorPages: </comment>';

    protected $magentoRootDir;

    /**
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer       = $composer;
        $this->io             = $io;
        $this->vendorDir      = rtrim($composer->getConfig()->get('vendor-dir'), '/');
        $this->packageExtra   = $composer->getPackage()->getExtra();
        $this->filesystem     = new Filesystem();
    }

    /**
     * Tell event dispatcher what events we want to subscribe to
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            InstallerEvents::POST_DEPENDENCIES_SOLVING => array(
                array('checkCoreDependencies', 0)
            ),
            PackageEvents::POST_PACKAGE_INSTALL => array(
                array('installCore', 0)
            ),
            PackageEvents::PRE_PACKAGE_UPDATE => array(
                array('uninstallCore', 0)
            ),
            PackageEvents::POST_PACKAGE_UPDATE => array(
                array('installCore', 0)
            ),
            PackageEvents::PRE_PACKAGE_UNINSTALL => array(
                array('uninstallCore', 0)
            ),
        );
    }

    /**
     * Check that there have core package install
     */
    public function checkCoreDependencies(InstallerEvent $event)
    {
        $installedCorePackages = array();
        foreach ($event->getInstalledRepo()->getPackages() as $package) {
            if ($package->getType() === 'magento-core') {
                $installedCorePackages[$package->getName()] = $package;
            }
        }

        $operations = array_filter($event->getOperations(), function (OperationInterface $o) {
            return in_array($o->getJobType(), array('install', 'uninstall'));
        });

        foreach ($operations as $operation) {
            $p = $operation->getPackage();
            switch ($operation->getJobType()) {
                case "uninstall":
                    unset($installedCorePackages[$p->getName()]);
                    break;
                case "install":
                    $installedCorePackages[$p->getName()] = $p;
                    break;
            }
        }
    }


    /**
     * @param PackageEvent $event
     */
    public function installCore(PackageEvent $event)
    {
        if (file_exists($this->getMagentoErrorDir()) && file_exists($this->getMagentoCustomErrorDir()) && !file_exists($this->getMagentoCustomErrorBackupDir())) {
            $this->io->write(
                sprintf(
                    '%s<info>Installing...</info>',
                    $this->ioPrefix
                )
            );

            $this->getInstaller()->install();

            $this->io->write(
                sprintf(
                    '%s<info>Done!</info>',
                    $this->ioPrefix
                )
            );
        }
    }

    /**
     * @param PackageEvent $event
     */
    public function uninstallCore(PackageEvent $event)
    {
        if (file_exists($this->getMagentoErrorDir()) && file_exists($this->getMagentoCustomErrorBackupDir())) {
            $this->io->write(
                sprintf(
                    '%s<info>Removing</info>',
                    $this->ioPrefix
                )
            );

            $this->getInstaller()->unInstall();

            $this->io->write(
                sprintf(
                    '%s<info>Done!</info>',
                    $this->ioPrefix
                )
            );
        }
    }

    /**
     * @return Installer
     */
    public function getInstaller()
    {
        $installer = new Installer($this->filesystem, $this->getMagentoRootDir());
        return $installer;
    }

    /**
     * Create root directory if it doesn't exist already
     *
     * @param string $dir
     */
    private function ensureDirExists($dir)
    {
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * @return string
     */
    public function getMagentoRootDir()
    {
        return isset($this->packageExtra['magento-root-dir']) ? rtrim($this->packageExtra['magento-root-dir'], "/") : '.';
    }

    /**
     * @return string
     */
    public function getMagentoErrorDir()
    {
        return $this->getMagentoRootDir() . DIRECTORY_SEPARATOR . 'errors';
    }

    /**
     * @return string
     */
    public function getMagentoCustomErrorDir()
    {
        return $this->getMagentoRootDir() . DIRECTORY_SEPARATOR . '.custom-errors';
    }

    /**
     * @return string
     */
    public function getMagentoCustomErrorBackupDir()
    {
        return $this->getMagentoCustomErrorDir() . DIRECTORY_SEPARATOR . '.backup-errors';
    }
}
