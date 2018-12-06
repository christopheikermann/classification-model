<?php declare(strict_types=1);

namespace PhpmlExamples;

include 'vendor/autoload.php';
include 'RandomForrest.php';
use Phpml\Dataset\CsvDataset;

ini_set('memory_limit', '2048M');

$dataset = new CsvDataset('data/person_original_train.csv', 9, true, ';');
$regressionModell = new RandomForrest();
$regressionModell->train($dataset->getSamples(), $dataset->getTargets(), $dataset->getColumnNames());

exit();

$dataset = new CsvDataset('data/person_original_predict.csv', 9, true, ';');
$samples = $dataset->getSamples();
$targets = $dataset->getTargets();

$regressionModell->evaluate($samples, $targets);

$regressionModell->filterByOccurrency(50)
    ->filterByLevel(3)
    ->filterByTarget('>50K')
    ->filterByAccuracy(0.9);

$regressionModell->printResult();

