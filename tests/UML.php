<?php

use Fhaculty\Graph\GraphViz;
use Fhaculty\Graph\Graph;
use Fhaculty\Graph\Uml\ClassDiagramBuilder;

// php tests/UML.php

require __DIR__ . '/bootstrap.php';

// initialize empty graph and an UML builder
$graph = new Graph();
$builder = new ClassDiagramBuilder($graph);

// Save trees to only show own methods and properties
$builder->setOption('only-self', TRUE);

// Make sure to show all stuff
$builder->setOption('show-private', TRUE);

// To generate only class items without relations set to FALSE
$builder->setOption('add-parents', TRUE);

$ignore = '/DrupalServer/i';
$match = '/[d|D]rush|Drupal/';
foreach ($loader->getClassMap() as $class => $file) {
  if (isset($ignore) && preg_match($ignore, $class)) {
    continue;
  }
  if (isset($match) && !preg_match($match, $class)) {
    continue;
  }
  echo "$class\n";
  $builder->createVertexClass($class);
}


$graphviz = new GraphViz($graph);
$graphviz->setFormat('svg');
// This is broken
//$graphviz->setLayout( GraphViz::LAYOUT_GRAPH, 'rankdir', 'TB');
$graphviz->display();
