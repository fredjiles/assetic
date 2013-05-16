<?php

/*
 * This file is part of the Assetic package, an OpenSky project.
 *
 * (c) 2010-2012 OpenSky Project Inc
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Assetic\Filter;

use Assetic\Asset\AssetInterface;
use Assetic\Util\ProcessBuilder;

/**
 * Loads LESS files.
 *
 * @author Kris Wallsmith <kris.wallsmith@gmail.com>
 */
class LessFilter implements FilterInterface
{
    private $nodeBin;
    private $nodePaths;
    private $compress;

    /**
     * Constructor.
     *
     * @param string $nodeBin   The path to the node binary
     * @param array  $nodePaths An array of node paths
     */
    public function __construct($nodeBin = '/usr/bin/node', array $nodePaths = array())
    {
        $this->nodeBin = $nodeBin;
        $this->nodePaths = $nodePaths;
    }

    public function setCompress($compress)
    {
        $this->compress = $compress;
    }

    public function filterLoad(AssetInterface $asset)
    {
        static $format = <<<'EOF'
var less = require('less');
var sys  = require(process.binding('natives').util ? 'util' : 'sys');
var fs = require('fs');

new(less.Parser)(%s).parse(%s, function(e, tree) {
    if (e) {
        less.writeError(e);
        process.exit(2);
    }

    try {
        var out = tree.toCSS(%s);
        fs.writeFile(%s, out, function(e){
            sys.print(out);
        });

    } catch (e) {
        less.writeError(e);
        process.exit(3);
    }
});

EOF;

        $root = $asset->getSourceRoot();
        $path = $asset->getSourcePath();

        // parser options
        $parserOptions = array();
        if ($root && $path) {
            $parserOptions['paths'] = array(dirname($root.'/'.$path));
            $parserOptions['filename'] = basename($path);
        }

        // tree options
        $treeOptions = array();
        if (null !== $this->compress) {
            $treeOptions['compress'] = $this->compress;
        }

        $pb = new ProcessBuilder();
        $pb->inheritEnvironmentVariables();

        // node.js configuration
        if (0 < count($this->nodePaths)) {
            $pb->setEnv('NODE_PATH', implode(':', $this->nodePaths));
        }

        $pb->setEnv('SystemPath', $_SERVER['SystemPath']);

        $pb->add($this->nodeBin)->add($input = tempnam(sys_get_temp_dir(), 'assetic_less'));
        $out = $input . ".css";

        file_put_contents($input, sprintf($format,
            json_encode($parserOptions),
            json_encode($asset->getContent()),
            json_encode($treeOptions),
            json_encode($out)
        ));

        $proc = $pb->getProcess();
        $code = $proc->run();
        unlink($input);

        if (0 < $code) {
            throw new \RuntimeException($proc->getErrorOutput());
        }

        if (DIRECTORY_SEPARATOR !== '/') {
            $asset->setContent(file_get_contents($out));
        }else{
            $asset->setContent($proc->getOutput());
        }

        unlink($out);

    }

    public function filterDump(AssetInterface $asset)
    {
    }
}
