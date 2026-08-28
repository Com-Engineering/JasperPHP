<?php

namespace JasperPHP\elements;

use JasperPHP\elements\Element;
use JasperPHP\core\Instructions;
use JasperPHP\elements\Report;
use JasperPHP\elements\PageFooter;
use JasperPHP\elements\PageHeader;

/**
 * Breaker class
 * This class handles page breaks and column breaks in a Jasper report.
 */
class Breaker extends Element
{
    public $printWhenExpression;

    public function __construct($objElement, $report = null)
    {
        parent::__construct($objElement, $report);
        $this->printWhenExpression = (string) $this->objElement->reportElement->printWhenExpression;
    }

    public function generate()
    {
        $print_expression_result = $this->report->evaluatePrintWhen(
            $this->printWhenExpression,
            $this->report->rowData,
            'Breaker'
        );

        if ($print_expression_result && Report::$proccessintructionsTime == 'inline') {
            $pageFooter = $this->report->getChildByClassName('PageFooter');
            if ($pageFooter) {
                $pageFooter->generate();
            }

            parent::generate();
            Instructions::addInstruction(["type" => "resetY_axis"]);
            Instructions::$currrentPage++;
            Instructions::addInstruction(["type" => "AddPage"]);
            Instructions::addInstruction(["type" => "setPage", "value" => Instructions::$currrentPage, 'resetMargins' => false]);

            $pageHeader = $this->report->getChildByClassName('PageHeader');
            if ($pageHeader) {
                $pageHeader->generate();
            }

            Instructions::runInstructions();
        } else {
            // 命令には置換済みの式を入れる（理由は Line.php と同じ）
            Instructions::addInstruction([
                "type" => "break",
                "printWhenExpression" => $this->report->get_expression(
                    $this->printWhenExpression,
                    $this->report->rowData
                ),
            ]);
            parent::generate();
        }
    }
}
