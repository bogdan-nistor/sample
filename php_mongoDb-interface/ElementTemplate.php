<?php
/**
 * Class ElementTemplate
 * @package     Configuration
 * @copyright   Copyright (c) 2015 Traffics Softwaresysteme für den Tourismus GmbH (http://www.traffics.de/)
 * @version     $Id: $
 * @author      Bogdan Nistor <bogdan.nistorb@yahoo.com>
 */
namespace Configuration;
use UnexpectedValueException;
use ArrayHelper;

class ElementTemplate extends Element
{
    const TEMPLATES = '__TEMPLATES__';
    /**
     * file content
     * may even be binary string
     * @var string $content
     */
    public $code;
    
    /**
     * media url
     * @var string $url
     */
    public $url;

    /**
     * placeholders in code
     * @var Property[] $data
     */
    public $properties = array();

    /**
     * nested templates
     * @var ElementTemplate[]
     */
    public $templates = array();


    /**
     * get generated content
     * @return string
     */
    public function getContent()
    {
        $content = $this->code;
        foreach($this->properties as $property) {
            $content = str_replace($property->name, $property->value, $content);
        }
        if (strpos($this->code, self::TEMPLATES) !== false) {
            $_content = '';
            foreach ($this->templates as $template) {
                $_content .= $template->getContent();
            }
            $content = str_replace(self::TEMPLATES, $_content, $content);
        }else {
            foreach ($this->templates as $template) {
                $content .= $template->getContent();
            }
        }
        return $content;
    }

    /**
     * @param string $id
     * @return Property|null
     */
    public function getProperty($id)
    {
        return ArrayHelper::getObject($this->properties, 'id', $id);
    }

    /**
     * @param Property $property
     * @return bool
     */
    public function deleteProperty(Property $property)
    {
        return ArrayHelper::deleteObject($this->properties, $property , 'id');
    }

    /**
     * clone nested objects
     */
    public function __clone()
    {
        $this->properties = ArrayHelper::cloneArray($this->properties);
    }

    /**
     * validation
     * @throws UnexpectedValueException
     */
    public function validate()
    {
        parent::validate();
        if (!is_array($this->properties)) {
            throw new UnexpectedValueException(__CLASS__ . "->properties is not an array");
        }
        if (!is_array($this->templates)) {
            throw new UnexpectedValueException(__CLASS__ . "->templates is not an array");
        }
    }
    
    /**
     * Template content
     * @return string
     */
    public function getTemplateText()
    {
        return $this->_buildTemplateCode(self);
    }
    
    /**
     * Build template php
     * 
     * @param \Configuration\ElementTemplate $templateParent
     * @param bool $buildChildren
     * 
     * @return string
     */
    private function _buildTemplateCode($templateParent, $buildChildren = false)
    {
        if ($buildChildren === false) {
            if (strpos($templateParent->code, self::TEMPLATES) !== false) {
                $code = str_replace(self::TEMPLATES, $this->_buildTemplateCode($templateParent, true),
                            $this->_buildTemplateProperties($templateParent->code, $templateParent->properties));
            } else {
                $code = $this->_buildTemplateProperties($templateParent->code, $templateParent->properties);
            }
        } else {
            foreach ($templateParent->templates as $template) {
                $code .= $this->_buildTemplateCode($template);
            }
        }
        return $code;
    }
    
    /**
     * Build template properties
     * 
     * @param string $templateCode
     * @param \Configuration\Property[] $properties
     * 
     * @return string
     */
    private function _buildTemplateProperties($templateCode, $properties)
    {
        if (is_array($properties)) {
            foreach ($properties as $property) {
                if (strpos($templateCode, $property->name) !== false) {
                    $templateCode = str_replace($property->name, $property->value, $templateCode);
                }
            }
        }
        return $templateCode;
    }
}
