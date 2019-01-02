<?php  declare(strict_types=1);

namespace Phpml\Regression;

use Phpml\Statistic\Statistic;

class RandomForrest {

    const KEY_SEPARATOR = '#';
    const VALUE_SEPARATOR = ':';
    protected $fields;
    protected $columnNames;

    protected $data;

    protected $dataSum;

    protected $valueCacheEncrypt = [];
    protected $valueCacheDecrypt = [];

    protected $valueCacheCount = 1;
    protected $mutationFields = [];

    /**
     * RandomForrest constructor.
     * @param array $samples
     * @param array $targets
     * @param array $columnNames
     */
    public function train(array $samples, array $targets, array $columnNames, $maxLevel=4)
    {
        $startTime = time();
        $this->columnNames = $columnNames;
        $fieldCount = count($this->columnNames);
        $this->mutationFields=$this->getMutations($fieldCount,$maxLevel);
        $this->data = [];
        $this->dataSum = [];

        echo "Mutationen: " . count($this->mutationFields) . "\n";
        echo "Samples: " . number_format(count($samples), 0, ',', '.') . "\n";
        echo "Datasets: " . number_format(count($this->mutationFields) * count($samples), 0, ',', '.') . "\n";

        foreach($this->columnNames as $fieldNumber => $fieldName) {
            $this->fields[$fieldNumber]['number'] = $fieldNumber;
            $this->fields[$fieldNumber]['name'] = $fieldName;
        }

        foreach($samples as $sampleNumber => $sample) {
            if ($sampleNumber % 500 == 1) {
                $this->printStatus(round(($sampleNumber/count($samples)),4)*100, $startTime);
            }

            foreach($this->mutationFields as $mutationField) {
                $value = '';
                $mutation = '';
                $level = count($mutationField);
                foreach($mutationField as $fieldNumber) {
                    $value.= $this->valueEncrypt($sample[$fieldNumber]).self::VALUE_SEPARATOR;
                    $mutation .= $fieldNumber.self::VALUE_SEPARATOR;
                }
                $this->data[$level.self::KEY_SEPARATOR.$mutation.self::KEY_SEPARATOR.$value][$this->valueEncrypt($targets[$sampleNumber])]++;
                $this->dataSum[$level.self::KEY_SEPARATOR.$mutation.self::KEY_SEPARATOR.$value]++;
            }
        }

        $this->printStatus(100);
        echo "\n\n";

    }

    /**
     * @param $percent
     * @param $startTime
     */
    public function printStatus($percent, $startTime = null)
    {
        if ($startTime) {
            $duration = time() - $startTime;
            $remainingTime = round(((100 / ($percent +0.001))-1) * $duration,0);
        } else {
            $remainingTime = 0;
        }

        echo "\r" . $percent . ' % (' . $this->convert(memory_get_usage()) . ", " . $remainingTime .  " s)     ";
    }

    /**
     * @param $fieldCount
     * @param $level
     * @return array
     */
    public function getMutations($fieldCount, $level)
    {
        $mutations = [];
        for ($iLevel=1; $iLevel<=$level; $iLevel++) {
            $mutations = array_merge($mutations, $this->getMutationLevel($fieldCount, $iLevel));
        }

        return $mutations;
    }

    /**
     * @param $fieldCount
     * @param $level
     * @return array
     */
    public function getMutationLevel($fieldCount, $level)
    {
        $mutations = [];
        $fields = [];
        for($i=0; $i<$level; $i++) {
            $fields[$i] = 1;
        }
        $fields[$level-1]=0;

        while($fields[0] < $fieldCount) {
            $fields[$level-1]++;

            // next
            for($i=$level-1; $i>=0; $i--) {
                if ($fields[$i] > $fieldCount && $i !== 0) {
                    $fields[$i]=1;
                    $fields[$i-1]++;
                }
            }

            // check if a > b > c > ..
            $valid = true;
            for($i=0; $i<$level-1; $i++) {
                if ($fields[$i] >= $fields[$i+1]) {
                    $valid = false;
                    break;
                }
            }

            if ($valid) {
                $mutations[] = $fields;
            }
        }

        return $mutations;
    }



    /**
     * @param array $sample
     * @param int $minLevel
     * @return array
     */
    public function predict(array $sample, $minLevel = 2)
    {
        $predictionFinding = [];
        $predictionSum = [];
        $predictions = [];
        $predictionCount = 0;
        $fieldCount = count($this->columnNames);

        foreach($this->mutationFields as $mutationField) {
            $value = '';
            $mutation = '';
            $level = count($mutationField);
            foreach($mutationField as $fieldNumber) {
                $value.= $this->valueEncrypt($sample[$fieldNumber]).self::VALUE_SEPARATOR;
                $mutation .= $fieldNumber.self::VALUE_SEPARATOR;
            }
            if ($level >= $minLevel && isset($this->data[$level.self::KEY_SEPARATOR.$mutation.self::KEY_SEPARATOR.$value])) {
                $predictionFinding[$level][$mutation] = $this->data[$level.self::KEY_SEPARATOR.$mutation.self::KEY_SEPARATOR.$value];
                $predictionSum[$level][$mutation] = $this->dataSum[$level.self::KEY_SEPARATOR.$mutation.self::KEY_SEPARATOR.$value];
            }
        }

        for($levelPrediction = $fieldCount; $levelPrediction > 1; $levelPrediction--) {
            if (isset($predictionFinding[$levelPrediction])) {
                $occurencyFilter = [];
                foreach($predictionFinding[$levelPrediction] as $mutationNumber => $predictionMutation) {
                    $occurencyFilter[] = $predictionSum[$levelPrediction][$mutationNumber];
                }
                $occurencyFilterValue = Statistic::percentileValue($occurencyFilter, 0.1);

                foreach($predictionFinding[$levelPrediction] as $mutationNumber => $predictionMutation) {
                    if ($predictionSum[$levelPrediction][$mutationNumber] >= $occurencyFilterValue) {
                        foreach ($predictionMutation as $target => $count) {
                            $predictions[$this->valueDecrypt($target)] += ($count / $predictionSum[$levelPrediction][$mutationNumber]) * (2^$levelPrediction);
                        }
                        $predictionCount += (2^$levelPrediction);
                    }
                }
            }
        }

        foreach($predictions as $target => $predictionValue) {
            $predictions[$target] = round($predictionValue/$predictionCount,3);
        }

        return $predictions;
    }

    /**
     * @param $samples
     * @param $targets
     */
    public function evaluate($samples, $targets)
    {
        $predictionAccuracy = [];
        $predictionRight=0;
        $predictionBetter90=0;
        $predictionBetter95=0;
        $predictionUnclear=0;
        $predictionClear=0;

        echo "\nPredictions: ".count($samples)."\n";
        foreach($samples as $key => $predictSample) {
            $prediction = $this->predict($predictSample);

            if (max($prediction) > 0.7) {
                $predictionAccuracy['N_'.strval(round($prediction[$targets[$key]],1))]++;

                if ($prediction[$targets[$key]] > 0.5) {
                    $predictionRight++;
                }
                if ($prediction[$targets[$key]] > 0.9) {
                    $predictionBetter90++;
                }
                if ($prediction[$targets[$key]] > 0.95) {
                    $predictionBetter95++;
                }
                $predictionClear++;
            } else {
                $predictionUnclear++;
            }
        }
        echo "Predictions unclear: ".round(($predictionUnclear/count($samples))*100)." %\n";
        echo "Predictions correct: ".round(($predictionRight/count($samples))*100)." %\n";
        echo "Predictions correct without unclear: ".round(($predictionRight/$predictionClear)*100)." %\n";
        echo "Predictions with better 90%: ".round(($predictionBetter90/count($samples))*100)." %\n";
        echo "Predictions with better 95%: ".round(($predictionBetter95/count($samples))*100)." %\n";
    }

    public function printResult()
    {
        foreach($this->data as $key => $sumTargets) {
            foreach($sumTargets as $target => $sum) {
                $percent = round(($sum/$this->dataSum[$key]),2);
                list($level, $mutation, $value) = explode(self::KEY_SEPARATOR, $key);
                $encryptValues = explode(self::VALUE_SEPARATOR, $value);
                $decrytedValue = '';
                foreach ($encryptValues as $encryptValue) {
                    $decrytedValue .= $this->valueDecrypt($encryptValue) . self::VALUE_SEPARATOR;
                }
                echo $key . ' => ' . $decrytedValue . ' => ' . $this->getMutationLabel($this->fields, $mutation) . ' => ' . $this->valueDecrypt($target) . ' => ' . $percent . " (" . $sum . ")\n";
            }
        }
    }

    /**
     * @param $value
     * @return int
     */
    protected function valueEncrypt($value)
    {
        if (is_numeric($value)) {
            $value = 'N_' . $value;
        }

        if (!isset($this->valueCacheEncrypt[$value])) {
            $this->valueCacheEncrypt[$value] = $this->valueCacheCount;
            $this->valueCacheDecrypt[$this->valueCacheCount] = $value;
            $this->valueCacheCount++;
        }

        return $this->valueCacheEncrypt[$value];
    }

    /**
     * @param $value
     * @return int
     */
    protected function valueDecrypt($value)
    {
        return $this->valueCacheDecrypt[$value];
    }


    /**
     * @param $occurency
     * @return $this
     */
    public function filterByOccurrency($occurency)
    {
        foreach($this->data as $key => $sumTargets) {
            if ($this->dataSum[$key] <= $occurency) {
                unset($this->data[$key]);
                unset($this->dataSum[$key]);
            }
        }

        return $this;
    }

    /**
     * @param $target
     * @return $this
     */
    public function filterByTarget($target)
    {
        $encryptTarget = $this->valueEncrypt($target);

        foreach($this->data as $key => $sumTargets) {
            foreach($sumTargets as $target => $sum) {
                if ($encryptTarget != $target) {
                    unset($this->data[$key][$target]);
                }
            }
        }

        return $this;
    }

    /**
     * @param $accuracy
     * @return $this
     */
    public function filterByAccuracy($accuracy)
    {
        foreach($this->data as $key => $sumTargets) {
            $allTargetAccuracyUnderperformance = true;
            foreach($sumTargets as $target => $sum) {
                if (!empty($target) && isset($this->dataSum[$key])) {
                    $percent = round(($sum/$this->dataSum[$key]),2);
                    if ($percent > $accuracy) {
                        $allTargetAccuracyUnderperformance = false;
                    } else {
                        unset($this->data[$key][$target]);
                    }
                }
            }

            if ($allTargetAccuracyUnderperformance) {
                unset($this->data[$key]);
                unset($this->dataSum[$key]);
            }
        }

        return $this;
    }

    /**
     * @param $maxLevel
     * @return $this
     */
    public function filterByLevel($maxLevel)
    {
        foreach($this->data as $key => $sumTargets) {
            list($level, $mutation, $value) = explode(self::KEY_SEPARATOR, $key);
            if ($level > $maxLevel) {
                unset($this->data[$key]);
                unset($this->dataSum[$key]);
            }
        }

        return $this;
    }

    protected function getMutationLabel($fields, $mutation) {
        $label='';
        foreach($fields as $fieldNumber => $field) {
            if ($mutation & $field['binary']) {
                $label.= $field['name'].':';
            }
        }
        return $label;
    }

    function convert($size)
    {
        $unit=array('b','kb','mb','gb','tb','pb');
        return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
    }
}
