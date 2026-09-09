<?php

namespace JasperPHP\elements;

use JasperPHP\elements\Element;
use JasperPHP\core\Instructions;
use JasperPHP\elements\Report;
use JasperPHP\elements\GroupHeader;
use JasperPHP\elements\GroupFooter;
use JasperPHP\core\Background;

/**
 * Detail class
 * This class represents the detail band in a Jasper report.
 */
class Detail extends Element
{
    public function __construct($objElement, $report = null)
    {
        parent::__construct($objElement, $report);
    }

    public function generate()
    {
        $dbData = $this->report->dbData;
        if (!$this->children || !$dbData) {
            return;
        }

        $rowIndex = 1;
        $totalRows = is_countable($dbData) ? count($dbData) : ($dbData->rowCount() ?: 0);
        $isDbDataArrayOrAccess = (is_array($dbData) || $dbData instanceof \ArrayAccess);
        
        // Initialize with the first row
		if($isDbDataArrayOrAccess){
			$this->report->rowData = $dbData[0] ?? null;
		}        
        $this->report->variables_calculation($this->report->rowData,$this->report->rowData);

        while ($this->report->rowData) {
            if (Report::$proccessintructionsTime == 'inline') {
                Instructions::runInstructions();
            }

            if (is_array($this->report->rowData)) {
                $this->report->rowData = (object) $this->report->rowData;
            }
            
            $this->report->rowData->rowIndex = $rowIndex;
            $this->report->arrayVariable['REPORT_COUNT']["ans"] = $rowIndex;
			$this->report->arrayVariable['REPORT_COUNT']['target'] = $rowIndex;
            $this->report->arrayVariable['REPORT_COUNT']['calculation'] = null;
			
            $this->report->arrayVariable['totalRows']["ans"] = $totalRows;
			$this->report->arrayVariable['totalRows']['target'] = $totalRows;
            $this->report->arrayVariable['totalRows']['calculation'] = null;

            // Remember which row is being laid out. Page breaks are only detected while
            // the instructions run, long after this loop is over, so without this the
            // report would still be pointing at the row that ended the loop (null) and
            // any band regenerated at break time would resolve $F{} to "".
            Instructions::addInstruction(["type" => "SetCurrentRow", "row" => $this->report->rowData]);

            // Group Headers
            if (!empty($this->report->arrayGroup)) {
                // Groups starting on this row: their header is printed below anyway, so a
                // page break happening right now must not reprint them on top of that.
                $startingGroups = [];
                foreach ($this->report->arrayGroup as $groupName => $group) {
                    if ($rowIndex == 1 || $group->resetVariables == 'true') {
                        $startingGroups[] = (string) $groupName;
                    }
                }
                if ($startingGroups) {
                    Instructions::addInstruction(["type" => "SetStartingGroups", "groups" => $startingGroups]);
                }

                foreach ($this->report->arrayGroup as $groupName => $group) {
                    if ($rowIndex != 1 && $group->resetVariables != 'true') {
                        continue;
                    }

                    // <group isStartNewPage="true"> : start every group (except the
                    // first one, which already sits on a fresh page) on a new page.
                    if ($rowIndex > 1 && ((string) $group['isStartNewPage']) === 'true') {
                        Instructions::addInstruction(["type" => "GroupPageBreak"]);
                        if (Report::$proccessintructionsTime == 'inline') {
                            Instructions::runInstructions();
                        }
                    }

                    if ($group->groupHeader) {
                        $groupHeader = new GroupHeader($group->groupHeader, $this->report);
                        $groupHeader->generate();
                    }

                    // Cleared for every group, not only the ones owning a header, so that
                    // a header-less group does not stay "resetting" for the rest of the run.
                    $group->resetVariables = 'false';
                }

                if ($startingGroups) {
                    Instructions::addInstruction(["type" => "SetStartingGroups", "groups" => []]);
                }
            }

            // Background
            $background = $this->report->getChildByClassName('Background');
            if ($background) {
                $background->generate();
            }

            // Detail content
            foreach ($this->children as $child) {
                if (is_object($child)) {
                    $print_expression_result = $this->evaluatePrintWhenExpression($child, $this->report->rowData);
                    
                    if ($print_expression_result) {
                        $this->generateChildElement($child);
                    }
                }
            }

            // Prepare for next iteration
            $this->report->lastRowData = $this->report->rowData;
            $recordObject = $this->report->arrayVariable['recordObj']['initialValue'] ?? "stdClass";
            $newRowData = $isDbDataArrayOrAccess ? ($dbData[$rowIndex] ?? null) : $dbData->fetchObject($recordObject);

            if (isset($this->report->lastRowData) && !empty($this->report->arrayGroup)) {
                foreach ($this->report->arrayGroup as $group) {
                    // The group break itself does not depend on owning a groupFooter;
                    // the footer is merely printed when there is one.
                    if (isset($group->groupExpression)) {
                        $currentGroupValue = $this->report->get_expression($group->groupExpression, $newRowData);
                        $previousGroupValue = $this->report->get_expression($group->groupExpression, $this->report->lastRowData);

                        if ($currentGroupValue != $previousGroupValue) {
                            if (isset($group->groupFooter)) {
                                $groupFooter = new \JasperPHP\elements\GroupFooter($group->groupFooter, $this->report);
                                $groupFooter->generate();
                            }
                            $group->resetVariables = 'true';
                        }
                    }
                }
            }
            $this->report->rowData = $newRowData;
            $this->report->variables_calculation($this->report->rowData,$this->report->rowData);
            $rowIndex++;
        }
    }

    private function evaluatePrintWhenExpression($element, $rowData)
    {
        return $this->report->evaluatePrintWhen(
            (string) $element->objElement->printWhenExpression,
            $rowData,
            'Detail'
        );
    }

    private function generateChildElement($element)
    {
        $height = (string) $element->objElement['height'];
        $splitType = (string) $element->objElement['splitType'];
        $isSplitTypeStretchOrPrevent = ($splitType == 'Stretch' || $splitType == 'Prevent');

        if ($isSplitTypeStretchOrPrevent) {
            Instructions::addInstruction(["type" => "PreventY_axis", "y_axis" => $height]);
        }

        if (Report::$proccessintructionsTime == 'inline') {
            Instructions::runInstructions();
        }

        $element->generate();

        if ($isSplitTypeStretchOrPrevent) {
            Instructions::addInstruction(["type" => "SetY_axis", "y_axis" => $height]);
        }
        
        if (Report::$proccessintructionsTime == 'inline') {
            Instructions::runInstructions();
        }

        if ($this->report->arrayPageSetting['columnCount'] > 1) {
            Instructions::addInstruction(["type" => "ChangeCollumn"]);
            if (($this->report->rowData->rowIndex % $this->report->arrayPageSetting['columnCount']) === 0) {
                Instructions::addInstruction(["type" => "SetY_axis", "y_axis" => $height]);
            }
        }
    }
}
