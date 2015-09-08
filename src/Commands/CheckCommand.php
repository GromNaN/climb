<?php

/*
 * This file is part of Climb.
 *
 * (c) Vincent Klaiber <hello@vinkla.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vinkla\Climb\Commands;

use League\CLImate\CLImate;
use Packagist\Api\Client;
use Stringy\StaticStringy;
use Stringy\Stringy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This is the check command class.
 *
 * @author Vincent Klaiber <hello@vinkla.com>
 */
class CheckCommand extends Command
{
    /**
     * Create a new check command instance.
     */
    public function __construct()
    {
        parent::__construct('check');

        $this->setDescription('Find newer versions of dependencies than what your composer.json allows');
    }

    /**
     * Execute the command.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return mixed
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $packages = $this->getPackages();

        $versions = $this->getVersions($packages);

        $climate = new CLImate();

        if (count($versions) <= 0) {
            return $climate->br()->out('All dependencies match the latest package versions <green>:)</green>')->br();
        }

        return $climate->br()->columns($versions, 3)->br();
    }

    /**
     * Get the required packages.
     *
     * @return array
     */
    private function getPackages()
    {
        $json = json_decode(file_get_contents(getcwd().'/composer.json'), true);

        $packages = array_merge($json['require'], $json['require-dev']);

        $array = [];

        foreach ($packages as $name => $version) {
            $string = new Stringy($name);

            if ($string->startsWith('php') || $string->startsWith('ext')) {
                continue;
            }

            $array[$name] = $version;
        }

        return $array;
    }

    /**
     * Get the versions.
     *
     * @param array $packages
     *
     * @return array
     */
    private function getVersions(array $packages)
    {
        $client = new Client();

        $versions = [];

        foreach ($packages as $name => $version) {
            $package = $client->get($name);

            $latest = $this->getLatest($package->getVersions());

            $current = $this->normalize($version);
            $latest = $this->compare($current, $this->normalize($latest));

            if (($latest || $latest !== '') && $current !== $latest) {
                array_push($versions, [$name, $current, '→', $latest]);
            }
        }

        return $versions;
    }

    /**
     * Get the latest version.
     *
     * @param array $versions
     *
     * @return string
     */
    private function getLatest(array $versions)
    {
        foreach ($versions as $version) {
            if (preg_match('/^v?\d\.\d(\.\d)?$/', $version->getVersion())) {
                return $version->getVersion();
            }
        }
    }

    /**
     * Normalize the version number.
     *
     * @param string $version
     *
     * @return string
     */
    private function normalize($version)
    {
        $version = preg_replace('/(v|\^|~)/', '', $version);

        if (preg_match('/^\d\.\d$/', $version)) {
            $version .= '.0';
        }

        return $version;
    }

    /**
     * Compare the current and latest versions.
     *
     * @param string $current
     * @param string $latest
     *
     * @return string
     */
    private function compare($current, $latest)
    {
        $current = str_split($current);
        $latest = str_split($latest);

        $version = '';
        $new = false;

        foreach ($current as $i => $character) {
            if (!isset($latest[$i])) {
                break;
            }

            if ($character !== $latest[$i]) {
                $new = true;
            }

            $version .= $new ? '<green>'.$latest[$i].'</green>' : $latest[$i];
        }

        return $version;
    }
}
