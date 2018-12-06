<?php  declare(strict_types=1);

namespace PhpmlExamples;

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

    /**
     * RandomForrest constructor.
     * @param array $samples
     * @param array $targets
     * @param array $columnNames
     */
    public function train(array $samples, array $targets, array $columnNames)
    {
        $this->columnNames = $columnNames;
        $fieldCount = count($this->columnNames);
        $mutations = pow(2,$fieldCount);
        $this->data = [];
        $this->dataSum = [];

        echo "Mutationen: ".$mutations."\n";
        echo "Samples: ".(count($samples))."\n";
        echo "Datasets: ".($mutations*count($samples))."\n";

        foreach($this->columnNames as $fieldNumber => $fieldName) {
            $this->fields[$fieldNumber]['binary'] = pow(2,$fieldNumber);
            $this->fields[$fieldNumber]['name'] = $fieldName;
        }

        foreach($samples as $sampleNumber => $sample) {
            if ($sampleNumber % 20 == 1) {
                echo "\r".(round(($sampleNumber/count($samples)),4)*100). ' % (' . $this->convert(memory_get_usage()) . ")";

                if ($sampleNumber > 5000 && $sampleNumber % 5000 == 1) {
                    $this->filterByOccurrency(1);
                }
            }

            $this->getMutations(4,2);
            exit();

            for ($mutation=1; $mutation < $mutations; $mutation++) {
                $value = '';
                $level = 0;
                foreach($this->fields as $fieldNumber => $field) {
                    if ($mutation & $field['binary']) {
                        $value.= $this->valueEncrypt($sample[$fieldNumber]).self::VALUE_SEPARATOR;
                        $level++;
                    }
                }
                $this->data[$level.self::KEY_SEPARATOR.$mutation.self::KEY_SEPARATOR.$value][$this->valueEncrypt($targets[$sampleNumber])]++;
                $this->dataSum[$level.self::KEY_SEPARATOR.$mutation.self::KEY_SEPARATOR.$value]++;
            }
        }
        echo "\n";

    }

    /**
     * @param $fieldCount
     * @param $level
     */
    public function getMutations($fieldCount, $level)
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
                }
            }

            if ($valid) {
                $mutations[] = $fields;
            }
        }

        var_dump($mutations);


        return $mutations;
    }



    /**
     * @param array $sample
     * @param int $minLevel
     * @return array
     */
    public function predict(array $sample, $minLevel = 5)
    {
        $predictionFinding = [];
        $predictionSum = [];
        $predictions = [];
        $predictionCount = 0;
        $fieldCount = count($this->columnNames);
        $mutations = pow(2,$fieldCount);

        for ($mutation=1; $mutation < $mutations; $mutation++) {
            $value = '';
            $level = 0;
            foreach($this->fields as $fieldNumber => $field) {
                if ($mutation & $field['binary']) {
                    $value.= $this->valueEncrypt($sample[$fieldNumber]).self::VALUE_SEPARATOR;
                    $level++;
                }
            }
            if ($level >= $minLevel && isset($this->data[$level.self::KEY_SEPARATOR.$mutation.self::KEY_SEPARATOR.$value])) {
                $predictionFinding[$level][$mutation] = $this->data[$level.self::KEY_SEPARATOR.$mutation.self::KEY_SEPARATOR.$value];
                $predictionSum[$level][$mutation] = $this->dataSum[$level.self::KEY_SEPARATOR.$mutation.self::KEY_SEPARATOR.$value];
            }
        }

        for($levelPrediction = $fieldCount; $levelPrediction > 1; $levelPrediction--) {
            if (isset($predictionFinding[$levelPrediction])) {
                foreach($predictionFinding[$levelPrediction] as $mutationNumber => $predictionMutation) {
                    foreach($predictionMutation as $target => $count) {
                        $predictions[$this->valueDecrypt($target)] += ($count / $predictionSum[$levelPrediction][$mutationNumber])*$levelPrediction;

                    }
                    $predictionCount+=$levelPrediction;
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

        echo "Predictions: ".count($samples)."\n";
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
