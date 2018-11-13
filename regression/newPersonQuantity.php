<?php declare(strict_types=1);

namespace PhpmlExamples;

include 'vendor/autoload.php';
include 'RandomForrest.php';
use Phpml\Dataset\CsvDataset;

ini_set('memory_limit', '2048M');

$dataset = new CsvDataset('data/person_original_train.csv', 9, true, ';');
$regressionModell = new RandomForrest();
$regressionModell->train($dataset->getSamples(), $dataset->getTargets(), $dataset->getColumnNames());

$dataset = new CsvDataset('data/person_original_predict.csv', 9, true, ';');
$samples = $dataset->getSamples();
$targets = $dataset->getTargets();
$predictionAccuracy = [];
$predictionRight=0;
$predictionBetter90=0;
$predictionBetter95=0;
$predictionUnclear=0;
$predictionClear=0;

echo "Predictions: ".count($samples)."\n";
foreach($samples as $key => $predictSample) {
    $prediction = $regressionModell->predict($predictSample);

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


$regressionModell->filterByOccurrency(50)
    ->filterByLevel(3)
    ->filterByTarget('>50K')
    ->filterByAccuracy(0.9);

$regressionModell->printResult();

