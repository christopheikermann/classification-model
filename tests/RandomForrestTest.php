<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

include "src/Regression/RandomForrest.php";


final class RandomForrestTest extends TestCase
{

    /**
     * @dataProvider mutationProvider
     */
    public function testMutations($fieldCount, $level, $expectMutations)
    {
        $randomForrest = new \Phpml\Regression\RandomForrest();

        $this->assertEquals($expectMutations, $randomForrest->getMutations($fieldCount, $level));
    }

    public function mutationProvider()
    {
        return [
            [3, 1, [[1],[2],[3]]],
            [3, 2, [[1],[2],[3],[1,2],[1,3],[2,3]]],
            [3, 3, [[1],[2],[3],[1,2],[1,3],[2,3],[1,2,3]]],
            [4, 2, [[1],[2],[3],[4],[1,2],[1,3],[1,4],[2,3],[2,4],[3,4]]],
        ];
    }
}