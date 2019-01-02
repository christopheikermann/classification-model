<?php

namespace Phpml\Statistic;

class Statistic
{
    public static function percentileValue($array, $percentile)
    {
        sort($array);
        $percentileCount = min(count($array), round($percentile * count($array),0)+1)-1;
        $percentileValue = $array[$percentileCount];

        return $percentileValue;
    }
}