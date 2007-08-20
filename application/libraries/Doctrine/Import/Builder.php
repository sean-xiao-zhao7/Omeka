<?php
/*
 *  $Id: Builder.php 2051 2007-07-23 20:28:46Z zYne $
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.phpdoctrine.com>.
 */

/**
 * Doctrine_Import_Builder
 * Import builder is responsible of building Doctrine ActiveRecord classes
 * based on a database schema.
 *
 * @package     Doctrine
 * @category    Object Relational Mapping
 * @link        www.phpdoctrine.com
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @since       1.0
 * @version     $Revision: 2051 $
 * @author      Konsta Vesterinen <kvesteri@cc.hut.fi>
 * @author      Jukka Hassinen <Jukka.Hassinen@BrainAlliance.com>
 * @author      Nicolas Bérard-Nault <nicobn@php.net>
 */
class Doctrine_Import_Builder
{
    /**
     * @var string $path    the path where imported files are being generated
     */
    private $path = '';

    private $suffix = '.php';

    private static $tpl;

    public function __construct()
    {
        $this->loadTemplate();
    }

    /**
     * setTargetPath
     *
     * @param string path   the path where imported files are being generated
     * @return
     */
    public function setTargetPath($path)
    {
        if ( ! file_exists($path)) {
            mkdir($path, 0777);
        }

        $this->path = $path;
    }
    /**
     * getTargetPath
     *
     * @return string       the path where imported files are being generated
     */
    public function getTargetPath()
    {
        return $this->path;
    }

    /**
     * This is a template that was previously in Builder/Record.tpl. Due to the fact
     * that it was not bundled when compiling, it had to be moved here.
     *
     * @return void
     */
    public function loadTemplate() 
    {
        if (isset(self::$tpl)) {
            return;
        }

        self::$tpl =<<<END
/**
 * This class has been auto-generated by the Doctrine ORM Framework
 */
class %s extends Doctrine_Record
{
    public function setTableDefinition()
    {
%s
    }
    public function setUp()
    {
%s
    }
}
END;

    }

    /*
     * Build the table definition of a Doctrine_Record object
     *
     * @param  string $table
     * @param  array  $tableColumns
     */
    public function buildColumnDefinition(array $tableColumns)
    {
        $columns   = array();
        $i = 1;

        foreach ($tableColumns as $name => $column) {
            $columns[$i] = '        $this->hasColumn(\'' . $name . '\', \'' . $column['type'] . '\'';
            if ($column['length']) {
                $columns[$i] .= ', ' . $column['length'];
            } else {
                $columns[$i] .= ', null';
            }

            $a = array();

            if (isset($column['default']) && $column['default']) {
                $a[] = '\'default\' => ' . var_export($column['default'], true);
            }
            if (isset($column['notnull']) && $column['notnull']) {
                $a[] = '\'notnull\' => true';
            }
            if (isset($column['primary']) && $column['primary']) {
                $a[] = '\'primary\' => true';
            }
            if (isset($column['autoinc']) && $column['autoinc']) {
                $a[] = '\'autoincrement\' => true';
            }
            if (isset($column['unique']) && $column['unique']) {
                $a[] = '\'unique\' => true';
            }
            if (isset($column['unsigned']) && $column['unsigned']) {
                $a[] = '\'unsigned\' => true';
            }
            if ($column['type'] == 'enum' && isset($column['values']) && $column['values']) {
                $a[] = '\'values\' => array(' . implode(',', $column['values']) . ')';
            }

            if ( ! empty($a)) {
                $columns[$i] .= ', ' . 'array(';
                $length = strlen($columns[$i]);
                $columns[$i] .= implode(',' . PHP_EOL . str_repeat(' ', $length), $a) . ')';
            }
            $columns[$i] .= ');';

            if ($i < (count($tableColumns) - 1)) {
                $columns[$i] .= PHP_EOL;
            }
            $i++;
        }
        
        return implode("\n", $columns);
    }
    public function buildRelationDefinition(array $relations)
    {
    	$ret = array();
    	$i = 0;
        foreach ($relations as $name => $relation) {
            $alias = (isset($relation['alias']) && $relation['alias'] !== $name) ? ' as ' . $relation['alias'] : '';

            if ( ! isset($relation['type'])) {
                $relation['type'] = Doctrine_Relation::ONE;
            }

            if ($relation['type'] === Doctrine_Relation::ONE || 
                $relation['type'] === Doctrine_Relation::ONE_COMPOSITE) {
                $ret[$i] = '        $this->hasOne(\'' . $name . $alias . '\'';
            } else {
                $ret[$i] = '        $this->hasMany(\'' . $name . $alias . '\'';
            }
            $a = array();

            if (isset($relation['deferred']) && $relation['deferred']) {
                $a[] = '\'default\' => ' . var_export($relation['deferred'], true);
            }
            if (isset($relation['local']) && $relation['local']) {
                $a[] = '\'local\' => ' . var_export($relation['local'], true);
            }
            if (isset($relation['foreign']) && $relation['foreign']) {
                $a[] = '\'foreign\' => ' . var_export($relation['foreign'], true);
            }
            if (isset($relation['onDelete']) && $relation['onDelete']) {
                $a[] = '\'onDelete\' => ' . var_export($relation['onDelete'], true);
            }
            if (isset($relation['onUpdate']) && $relation['onUpdate']) {
                $a[] = '\'onUpdate\' => ' . var_export($relation['onUpdate'], true);
            }
            if ( ! empty($a)) {
                $ret[$i] .= ', ' . 'array(';
                $length = strlen($ret[$i]);
                $ret[$i] .= implode(',' . PHP_EOL . str_repeat(' ', $length), $a) . ')';
            }
            $ret[$i] .= ');';
            $i++;
        }
        return implode("\n", $ret);
    }
    

    public function buildDefinition(array $options, array $columns, array $relations = array())
    {
    	if ( ! isset($options['className'])) {
    	    throw new Doctrine_Import_Builder_Exception('Missing class name.');
    	}

        //$opt     = array(0 => str_repeat(' ', 8) . '$this->setTableName(\''. $table .'\');');

        $content = sprintf(self::$tpl, $options['className'],
                          $this->buildColumnDefinition($columns),
                          $this->buildRelationDefinition($relations));
                          
        return $content;
    }

    public function buildRecord($options, $columns, $relations)
    {
    	if ( ! isset($options['className'])) {
    	    throw new Doctrine_Import_Builder_Exception('Missing class name.');
    	}

        if ( ! isset($options['fileName'])) {
            if (empty($this->path)) {
                $errMsg = 'No build target directory set.';
                throw new Doctrine_Import_Builder_Exception($errMsg);
            }
            

            if (is_writable($this->path) === false) {
                $errMsg = 'Build target directory ' . $this->path . ' is not writable.';
                throw new Doctrine_Import_Builder_Exception($errMsg);
            }

            $options['fileName']  = $this->path . DIRECTORY_SEPARATOR . $options['className'] . $this->suffix;
        }

        $content = $this->buildDefinition($options, $columns, $relations);

        $bytes = file_put_contents($options['fileName'], '<?php' . PHP_EOL . $content);

        if ($bytes === false) {
            throw new Doctrine_Import_Builder_Exception("Couldn't write file " . $options['fileName']);
        }
    }
}
