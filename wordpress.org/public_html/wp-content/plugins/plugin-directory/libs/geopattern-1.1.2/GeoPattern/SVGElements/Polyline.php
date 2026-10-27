<?php namespace RedeyeVentures\GeoPattern\SVGElements;

class Polyline extends Base
{
    protected $tag = 'polyline';

    function __construct($points, $args=array())
    {
        $this->elements = [
            'points' => $this->pairPoints($points),
        ];
        parent::__construct($args);
    }

    // WordPress.org: emit "points" as space-delimited x,y pairs (e.g. "3,0 6,0") instead of one
    // flat comma-separated list of every number. The paired form is the conventional, parser-safe
    // encoding. See https://meta.trac.wordpress.org/ticket/8270.
    protected function pairPoints($points)
    {
        if (!preg_match_all('/-?\d*\.?\d+/', $points, $matches)) {
            return $points;
        }
        $numbers = $matches[0];
        $pairs = [];
        for ($i = 0; $i + 1 < count($numbers); $i += 2) {
            $pairs[] = $numbers[$i].','.$numbers[$i + 1];
        }
        return implode(' ', $pairs);
    }
}