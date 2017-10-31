<?php

/**
 * @Author: anhproduction
 * @Date:   2017-10-27 16:22:09
 * @Last Modified by:   Nguyen Duc Ngoc Anh
 * @Last Modified time: 2017-10-30 16:52:32
 */
namespace AnhNDN\MageCustomErrorPages;

use Composer\Composer;
use Composer\Script\Event;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use Composer\Plugin\PluginInterface;
use Composer\Installer\CommandEvent;
use Composer\Installer\PackageEvent;
use Composer\Installer\CommandEvents;
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
     * @var string
     */
    protected $isInstall = true;

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
            'post-install-cmd' => array(
                array('installCustomize', 0)
            ),
            'post-update-cmd' => array(
                array('installCustomize', 0)
            ),
            PackageEvents::POST_PACKAGE_INSTALL => array(
                array('installPackageCustomize', 0)
            ),
            PackageEvents::POST_PACKAGE_UPDATE => array(
                array('installPackageCustomize', 0)
            ),
            PackageEvents::PRE_PACKAGE_UNINSTALL => array(
                 array('uninstallCustomize', 0)
             ),
        );
    }

    public function installCustomize($event)
    {
        if ($this->isInstalled())                                 return;
        if (!$this->isInstall)                                    return;
        if (file_exists($this->getMagentoCustomErrorBackupDir())) return;
        if (!file_exists($this->getMagentoErrorDir()))            return;
        if (!file_exists($this->getMagentoCustomErrorDir()))      return;

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

    /**
     * @param PackageEvent $event
     */
    public function installPackageCustomize(PackageEvent $event)
    {
        switch ($event->getOperation()->getJobType()) {
            case "install":
                $package = $event->getOperation()->getPackage();
                break;
            case "update":
                $package = $event->getOperation()->getTargetPackage();
                break;
        }

        if (!isset($package)) return;
        $extra = $package->getExtra();

        if (!isset($extra) || !is_array($extra)) return;
        if (!isset($extra['class']))             return;
        if (!is_string($extra['class']))         return;
        $class = ltrim($extra['class'], "\\");

        if ($class != get_class($this))                     return;
        if (file_exists($this->getMagentoErrorDir()))       return;
        if (file_exists($this->getMagentoCustomErrorDir())) return;

        $this->isInstall = true;
    }

    /**
     * @param PackageEvent $event
     */
    public function uninstallCustomize(PackageEvent $event)
    {
        if ($event->getOperation()->getJobType() !== "uninstall") return;

        $extra = $event->getOperation()->getPackage()->getExtra();

        if (!$extra || !is_array($extra)) return;
        if (!isset($extra['class']))      return;
        if (!is_string($extra['class']))  return;
        $class = ltrim($extra['class'], "\\");

        if ($class != get_class($this))                            return;
        if (!file_exists($this->getMagentoErrorDir()))             return;
        if (!file_exists($this->getMagentoCustomErrorBackupDir())) return;

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
        $this->isInstall = false;
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

    /**
     * @return boolean
     */
    public function isInstalled()
    {
        return file_exists($this->getMagentoErrorDir() . DIRECTORY_SEPARATOR . '.isInstalled');
    }
}
