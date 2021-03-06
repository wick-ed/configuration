<?php

/**
 * \AppserverIo\Configuration\Configuration
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @author     Tim Wagner <tw@techdivision.com>
 * @copyright  2015 TechDivision GmbH <info@techdivision.com>
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link       http://github.com/appserver-io/configuration
 * @link       http://www.appserver.io
 */

namespace AppserverIo\Configuration;

use AppserverIo\Configuration\Interfaces\ConfigurationInterface;
use AppserverIo\Configuration\Interfaces\PersistableConfigurationInterface;
use AppserverIo\Configuration\Interfaces\ValidityAwareConfigurationInterface;

/**
 * A simple XML based configuration implementation.
 *
 * @author     Tim Wagner <tw@techdivision.com>
 * @copyright  2015 TechDivision GmbH <info@techdivision.com>
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link       http://github.com/appserver-io/configuration
 * @link       http://www.appserver.io
 */
class Configuration implements ConfigurationInterface, ValidityAwareConfigurationInterface, PersistableConfigurationInterface
{

    /**
     * XSD schema filename used for validation.
     *
     * @var string
     */
    protected $schemaFile;

    /**
     * the node name to use.
     *
     * @var string
     */
    protected $nodeName;

    /**
     * The node value.
     *
     * @var string
     */
    protected $value;

    /**
     * The array with configuration parameters.
     *
     * @var array
     */
    protected $data = array();

    /**
     * The array with the child configurations.
     *
     * @var array
     */
    protected $children = array();

    /**
     * Initializes the configuration with the node name of the
     * node in the XML structure.
     *
     * @param string $nodeName The configuration element's node name
     */
    public function __construct($nodeName = null)
    {
        $this->setNodeName($nodeName);
    }

    /**
     * Sets the configuration element's node name.
     *
     * @param string $nodeName The node name
     *
     * @return \AppserverIo\Configuration\Interfaces\ConfigurationInterface The instance itself
     */
    public function setNodeName($nodeName)
    {
        $this->nodeName = $nodeName;
    }

    /**
     * Returns the configuration element's node name.
     *
     * @return string The node name
     */
    public function getNodeName()
    {
        return $this->nodeName;
    }

    /**
     * Checks if the passed configuration is equal. If yes, the method
     * returns TRUE, if not FALSE.
     *
     * @param \AppserverIo\Configuration\Interfaces\ConfigurationInterface $configuration The configuration to compare to
     *
     * @return boolean TRUE if the configurations are equal, else FALSE
     */
    public function equals(ConfigurationInterface $configuration)
    {
        return $this === $configuration;
    }

    /**
     * Adds a new child configuration.
     *
     * @param \AppserverIo\Configuration\Interfaces\ConfigurationInterface $configuration The child configuration itself
     *
     * @return \AppserverIo\Configuration\Interfaces\ConfigurationInterface The configuration instance
     */
    public function addChild(ConfigurationInterface $configuration)
    {
        $this->children[] = $configuration;
        return $this;
    }

    /**
     * Creates a new child configuration node with the passed name and value
     * and adds it as child to this node.
     *
     * @param string $nodeName The child's node name
     * @param string $value    The child's node value
     *
     * @return void
     */
    public function addChildWithNameAndValue($nodeName, $value)
    {
        $node = new Configuration();
        $node->setNodeName($nodeName);
        $node->setValue($value);
        $this->addChild($node);
    }

    /**
     * Initializes the configuration with the XML information found
     * in the file with the passed relative or absolute path.
     *
     * @param string $file The path to the file
     *
     * @return \AppserverIo\Configuration\Interfaces\ConfigurationInterface The initialized configuration
     * @throws \Exception Is thrown if the file with the passed name is not a valid XML file
     */
    public function initFromFile($file)
    {
        if (($root = simplexml_load_file($file)) === false) {
            $errors = array();
            foreach (libxml_get_errors() as $error) {
                $errors[] = sprintf(
                    'Found a schema validation error on line %s with code %s and message %s when validating configuration file %s',
                    $error->line,
                    $error->code,
                    $error->message,
                    $error->file
                );
            }
            throw new \Exception(implode(PHP_EOL, $errors));
        }
        return $this->init($root);
    }

    /**
     * Initializes the configuration with the XML information passed as string.
     *
     * @param string $string The string with the XML content to initialize from
     *
     * @return \AppserverIo\Configuration\Interfaces\ConfigurationInterface The initialized configuration
     * @throws \Exception Is thrown if the passed XML string is not valid
     */
    public function initFromString($string)
    {
        if (($root = simplexml_load_string($string)) === false) {
            $errors = array();
            foreach (libxml_get_errors() as $error) {
                $errors[] = sprintf(
                    'Found a schema validation error on line %s with code %s and message %s when validating configuration file %s',
                    $error->line,
                    $error->code,
                    $error->message,
                    $error->file
                );
            }
            throw new \Exception(implode(PHP_EOL, $errors));
        }
        return $this->init($root);
    }

    /**
     * Initializes the configuration with the XML information found
     * in the passed DOMDocument.
     *
     * @param \DOMDocument $domDocument The DOMDocument with XML information
     *
     * @return \AppserverIo\Configuration\Interfaces\ConfigurationInterface The initialized configuration
     */
    public function initFromDomDocument(\DOMDocument $domDocument)
    {
        $root = simplexml_import_dom($domDocument);
        return $this->init($root);
    }

    /**
     * Recursively initializes the configuration instance with the data from the
     * passed SimpleXMLElement.
     *
     * @param \SimpleXMLElement $node The node to load the data from
     *
     * @return \AppserverIo\Configuration\Interfaces\ConfigurationInterface The node instance itself
     */
    public function init(\SimpleXMLElement $node)
    {

        // set the node name + value
        $this->setNodeName($node->getName());

        // check if we found a node value
        $nodeValue = (string) $node;
        if (empty($nodeValue) === false) {
            $this->setValue(trim($nodeValue));
        }

        // load the attributes
        foreach ($node->attributes() as $key => $value) {
            $this->setData($key, (string) $value);
        }

        // append children
        foreach ($node->children() as $child) {
            // create a new configuration node
            $cnt = new Configuration();

            // parse the configuration recursive
            $cnt->init($child);

            // append the configuration node to the parent
            $this->addChild($cnt);
        }

        // return the instance node itself
        return $this;
    }

    /**
     * Returns the child configuration nodes with the passed type.
     *
     * @param string $path The path of the configuration to return
     *
     * @return array The requested child configuration nodes
     */
    public function getChilds($path)
    {
        $token = strtok($path, '/');
        $next = substr($path, strlen('/' . $token));
        if ($this->getNodeName() == $token && empty($next)) {
            return $this;
        } elseif ($this->getNodeName() == $token && empty($next) === false) {
            $matches = array();
            foreach ($this->getChildren() as $child) {
                $result = $child->getChilds($next);
                if (is_array($result)) {
                    $matches = $result;
                } elseif ($result instanceof Configuration) {
                    $matches[] = $result;
                }
            }
            return $matches;
        } else {
            return null;
        }
    }

    /**
     * Returns the child configuration with the passed type.
     *
     * @param string $path The path of the configuration to return
     *
     * @return \AppserverIo\Configuration\Interfaces\ConfigurationInterface The requested configuration
     */
    public function getChild($path)
    {
        if (is_array($childs = $this->getChilds($path))) {
            return current($childs);
        }
    }

    /**
     * Removes the children of the configuration with passed path and
     * returns the parent configuration.
     *
     * @param string $path The path of the configuration to remove the children for
     *
     * @return \AppserverIo\Configuration\Interfaces\ConfigurationInterface The instance the children has been removed
     * @see \AppserverIo\Configuration\Interfaces\ConfigurationInterface::getChild($path)
     * @see \AppserverIo\Configuration\Interfaces\ConfigurationInterface::getChilds($path)
     */
    public function removeChilds($path)
    {
        $token = strtok($path, '/');
        $next = substr($path, strlen('/' . $token));
        if ($this->getNodeName() == $token && empty($next) === false) {
            $this->setChildren(array());
            return $this;
        } else {
            return $this;
        }
    }

    /**
     * Returns all child configurations.
     *
     * @return array The child configurations
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * Replaces actual children with the passed array. If children
     * already exists they will be lost.
     *
     * @param array $children The array with the children to set
     *
     * @return void
     */
    public function setChildren(array $children)
    {
        $this->children = $children;
    }

    /**
     * Check's if the node has children, if yes the method
     * returns TRUE, else the method returns FALSE.
     *
     * @return boolean TRUE if the node has children, else FALSE
     */
    public function hasChildren()
    {
        // check the children size
        if (sizeof($this->getChildren()) == 0) {
            return false;
        }
        return true;
    }

    /**
     * Adds the passed configuration value.
     *
     * @param string $key   Name of the configuration value
     * @param mixed  $value The configuration value
     *
     * @return void
     */
    public function setData($key, $value)
    {
        $this->data[$key] = $value;
    }

    /**
     * Returns the configuration value with the passed name.
     *
     * @param string $key The name of the requested configuration value.
     *
     * @return mixed The configuration value itself
     */
    public function getData($key)
    {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }
    }

    /**
     * Appends the passed attributes to the configuration node. If the
     * attribute already exists it will be overwritten by default.
     *
     * @param array $data The data with the attributes to append
     *
     * @return void
     */
    public function appendData(array $data)
    {
        foreach ($data as $key => $value) {
            $this->data[$key] = $value;
        }
    }

    /**
     * Replaces actual attributes with the passed array. If attributes
     * already exists they will be lost.
     *
     * @param array $data The array with the key value attribute pairs
     *
     * @return void
     */
    public function setAllData($data)
    {
        $this->data = $data;
    }

    /**
     * Returns all attributes.
     *
     * @return array The array with all attributes
     */
    public function getAllData()
    {
        return $this->data;
    }

    /**
     * Wrapper method for getter/setter methods.
     *
     * @param string $method The called method name
     * @param array  $args   The methods arguments
     *
     * @return mixed The value if a getter has been invoked
     * @throws \Exception Is thrown if nor a getter/setter has been invoked
     */
    public function __call($method, $args)
    {
        // lowercase the first character of the member
        $key = lcfirst(substr($method, 3));
        // check if a getter/setter has been called
        switch (substr($method, 0, 3)) {
            case 'get':
                $child = $this->getChild("/{$this->getNodeName()}/$key");
                if ($child instanceof Configuration) {
                    return $child;
                }
                return $this->getData($key);
            case 'set':
                $this->setData($key, isset($args[0]) ? $args[0] : null);
                break;
            default:
                throw new \Exception("Invalid method " . get_class($this) . "::" . $method . "(" . print_r($args, true) . ")");
        }
    }

    /**
     * Sets the configuration node's value e.g. <node>VALUE</node>.
     *
     * @param string $value The node's value
     *
     * @return void
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     * Returns the configuration node's value.
     *
     * @return string The node's value
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Returns the configuration node's value.
     *
     * @return string The configuration node's value
     */
    public function __toString()
    {
        return $this->getValue();
    }

    /**
     * Merge the configuration
     *
     * @param \AppserverIo\Configuration\Interfaces\ConfigurationInterface $configuration A configuration to merge
     *
     * @return \AppserverIo\Configuration\Interfaces\ConfigurationInterface The instance
     */
    public function merge(ConfigurationInterface $configuration)
    {
        if ($this->hasSameSignature($configuration)) {
            $this->setValue($configuration->getValue());
            $this->setAllData($configuration->getAllData());
            if ($configuration->hasChildren()) {
                foreach ($configuration->getChildren() as $child) {
                    $path = $this->getNodeName() . "/" . $child->getNodeName();
                    if ($this->getChild($path)) {
                        if ($newChild = $this->getChild($path)->merge($child)) {
                            $this->addChild($newChild);
                        }
                    } else {
                        $this->addChild($child);
                    }
                }
            }
        } else {
            return $configuration;
        }
    }

    /**
     * Merges this configuration element with the data found in the passed configuration file.
     *
     * @param string $file The path to the file we want to merge
     *
     * @return \AppserverIo\Configuration\Interfaces\ConfigurationInterface The configuration created from the passed file
     */
    public function mergeFromFile($file)
    {

        // initialize a new configuration instance
        $configuration = new Configuration();
        $configuration->initFromFile($file);

        // merge the instance with this one
        $this->merge($configuration);

        // return this instance
        return $configuration;
    }

    /**
     * Merges this configuration element with the passed configuration data.
     *
     * @param string $string The string with the XML content to initialize from
     *
     * @return \AppserverIo\Configuration\Interfaces\ConfigurationInterface The configuration created from the passed string
     */
    public function mergeFromString($string)
    {

        // initialize a new configuration instance
        $configuration = new Configuration();
        $configuration->initFromString($string);

        // merge the instance with this one
        $this->merge($configuration);

        // return this instance
        return $configuration;
    }

    /**
     * Returns the node signature using a md5 hash based on
     * the node name and the param data.
     *
     * @return string The node signature as md5 hash
     */
    public function getSignature()
    {
        return md5($this->getNodeName() . implode('', $this->getAllData()));
    }

    /**
     * Returns TRUE if the node signatures are equals, else FALSE
     *
     * @param \AppserverIo\Configuration\Interfaces\ConfigurationInterface $configuration The configuration node to check the signature
     *
     * @return boolean TRUE if the signature of the passed node equals the signature of this instance, else FALSE
     */
    public function hasSameSignature(ConfigurationInterface $configuration)
    {
        return $this->getSignature() === $configuration->getSignature();
    }

    /**
     * Saves the configuration node recursively to the
     * file with the passed name.
     *
     * @param string $filename The filename to save the configuration node to
     *
     * @return void
     */
    public function save($filename)
    {
        $this->toDomDocument()->save($filename);
    }

    /**
     * Creates and returns a DOM document by recursively parsing
     * the configuration node and it's childs.
     *
     * @param string $namespaceURI The dom document namespace
     *
     * @return \DOMDocument The configuration node as DOM document
     */
    public function toDomDocument($namespaceURI = 'http://www.appserver.io/appserver')
    {
        $domDocument = new \DOMDocument('1.0', 'UTF-8');
        $domDocument->appendChild($this->toDomElement($domDocument, $namespaceURI));
        return $domDocument;
    }

    /**
     * Sets the filename of the schema file used for validation.
     *
     * @param string $schemaFile Filename of the schema for validation of the configuration node
     *
     * @return void
     */
    public function setSchemaFile($schemaFile)
    {
        $this->schemaFile = $schemaFile;
    }

    /**
     * Returns the filename of the schema file used for validation.
     *
     * @return string The filename of the schema file used for validation
     */
    public function getSchemaFile()
    {
        return $this->schemaFile;
    }

    /**
     * Recursively creates and returns a DOM element of this configuration node.
     *
     * @param \DOMDocument $domDocument  The DOM document necessary to create a \DOMElement instance
     * @param string       $namespaceURI The namespace URI to use
     *
     * @return \DOMElement The initialized DOM element
     */
    public function toDomElement(\DOMDocument $domDocument, $namespaceURI = null)
    {
        // if a namespace URI was given, create namespaced DOM element
        if ($namespaceURI) {
            $domElement = $domDocument->createElementNS($namespaceURI, $this->getNodeName(), $this->getValue());
        } else {
            $domElement = $domDocument->createElement($this->getNodeName(), $this->getValue());
        }

        // append the element's attributes
        foreach ($this->getAllData() as $key => $value) {
            $domElement->setAttribute($key, $value);
        }

        // append the element's child nodes
        foreach ($this->getChildren() as $child) {
            $domElement->appendChild($child->toDomElement($domDocument));
        }

        // return the
        return $domElement;
    }

    /**
     * Validates the configuration node against the schema file.
     *
     * @throws \Exception Is thrown if the validation was not successful
     * @return \DOMDocument The validated DOM document
     * @see \AppserverIo\Configuration\Configuration::setSchemaFile()
     */
    public function validate()
    {

        // check if a schema file was specified and exists
        if ($this->getSchemaFile() == null) {
            throw new \Exception("Missing XSD schema file for validation");
        }
        if (file_exists($this->getSchemaFile()) === false) {
            throw new \Exception(sprintf("XSD schema file %s for validation not available", $this->getSchemaFile()));
        }

        // activate internal error handling, necessary to catch errors with libxml_get_errors()
        libxml_use_internal_errors(true);

        // recursively create a DOM document from the configuration node and validate it
        $domDocument = $this->toDomDocument();
        if ($domDocument->schemaValidate($this->getSchemaFile()) === false) {
            foreach (libxml_get_errors() as $error) {
                $message = "Found a schema validation error on line %s with code %s and message %s when validating configuration file %s";

                throw new \Exception(sprintf($message, $error->line, $error->code, $error->message, $error->file));
            }
        }
        return $domDocument;
    }
}
