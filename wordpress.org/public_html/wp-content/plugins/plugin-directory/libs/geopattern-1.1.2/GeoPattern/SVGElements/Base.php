<?php namespace RedeyeVentures\GeoPattern\SVGElements;

abstract class Base
{
    protected $tag;
    protected $elements;
    protected $args;

    function __construct($args)
    {
        $this->args = $args;
    }

    // WordPress.org: round generated floats (opacity, coordinates, transforms) to 3 decimal
    // places. The upstream generator emits full PHP precision, e.g. fill-opacity="0.080666666666667",
    // which is invisible once rendered but inflates every icon. See https://meta.trac.wordpress.org/ticket/8270.
    protected function trimPrecision($value)
    {
        return preg_replace_callback(
            '/-?\d*\.\d+/',
            function ($matches) {
                return rtrim(rtrim(number_format((float) $matches[0], 3, '.', ''), '0'), '.');
            },
            (string) $value
        );
    }

    public function elementsToString()
    {
        $string = ' ';
        foreach ($this->elements as $key => $value)
        {
            $string .= "$key=\"".$this->trimPrecision($value)."\" ";
        }
        return $string;
    }

    public function argsToString()
    {
        $string = '';
        foreach ($this->args as $key => $value)
        {
            if (is_array($value))
            {
                $string .= "$key=\"";
                foreach ($value as $k => $v)
                {
                    $string .= "$k:".$this->trimPrecision($v).";";
                }
                $string .= '" ';
            }
            else
            {
                $string .= "$key=\"".$this->trimPrecision($value)."\" ";
            }
        }
        return $string;
    }

    public function getString()
    {
        return "<{$this->tag}{$this->elementsToString()}{$this->argsToString()}/>";
    }

    function __toString()
    {
        return $this->getString();
    }
}