<?php
// no direct access
defined('_JLMS_EXEC') or die('Restricted access');

class JLMS_SummaryReports_html
{

    static function showSummaryReport($rows, $courses, $pageNav, $lists)
    {
        global $Itemid;
        $link = "index.php?option=com_joomla_lms&task=default&Itemid=$Itemid&activetab=jlmsTabSummaryReport";
        ?>
        <script type="text/javascript">
            jQuery(function () {
                jQuery('#summary-report-download').on('click', set);
                jQuery('#summary-report-search').on('click', unset);
                function set() {
                    jQuery('#download-summary-report').attr('value', 'excel');
                };
                function unset() {
                    jQuery('#download-summary-report').attr('value', '');
                };

            });
        </script>
        <h2>Summary statistics</h2>
        <form
            action="<?php echo sefRelToAbs($link); ?>"
            method="post" id="report-form-action" name="reportForm">

            <?php
            $filtertop = new \LMS\Widgets\Filters();
            $filtertop->addFilter($pageNav->GetLimitBox($link));
            $filtertop->addFilter('<button class="btn tip hasTooltip" id="summary-report-search" type="submit" title="' . JText::_('JSEARCH_FILTER_SUBMIT') . '"><i class="icon-search"></i></button>');
            $filtertop->addFilter('<button class="btn tip hasTooltip" id="summary-report-download" type="submit" title="Download summary report"><i class="icon-download"></i></button>');
            $filtertop->addFilter($lists['group']);
            $filtertop->addFilter($lists['course']);
            echo $filtertop;
            ?>

            <table width="100%" cellpadding="0" cellspacing="0" border="0" class="table table-striped table-hover">
                <thead>
                <tr>
                    <th>
                        Ref<br>Firstname Lastname
                    </th>
                    <th align="center">
                        Status/Course
                    </th>
                    <th align="center">
                        Department
                    </th>
                    <th align="center">
                        Organisation
                    </th>
                    <th align="center">
                        Senior Manager
                    </th>
                </tr>
                </thead>
                <?php
                foreach ($rows as $row) {
                    ?>
                    <tr>
                        <td>
                            <?php echo $row->username; ?>
                            <br>
                            <?php echo $row->name; ?>
                        </td>
                        <td nowrap="nowrap">
                            <?php
                            $completed_courses = $row->completed_courses;
                            foreach ($courses as $course) {
                                //echo $course->course_name.': '.(in_array($course->id,$completed_courses)?'Yes':'No').'<br/>';
                                echo (in_array($course->id, $completed_courses) ? '<i class="icon-ok"></i>' : '<i class="icon-cancel"></i>') . $course->course_name . '<br/>';
                            }
                            ?>
                        </td>
                        <td align="center">
                            <?php echo $row->ug_name; ?>
                        </td>
                        <td align="center">
                            <?php echo $row->subgroup_name; ?>
                        </td>
                        <td align="center">
                            <?php echo $row->manager_name; ?>
                        </td>
                    </tr>
                <?php
                }
                ?>
                <tfoot>
                <tr>
                    <div class="row-fluid">
                        <div class="pagination center">
                            <td colspan="7">
                                <?php echo $pageNav->getListFooter(); ?>
                            </td>
                        </div>
                    </div>
                </tr>
                </tfoot>
            </table>
            <input type="hidden" id="download-summary-report" name="download-summary-report" value=""/>
        </form>

        <div>
            <h2>Parent groups statistics</h2>
            <table width="100%" cellpadding="0" cellspacing="0" border="0"  class="table table-striped table-hover">
                <tr>
                    <th>Staff</th>
                    <th>Excluded Staff</th>
                    <th></th>
                    <th>Group/Course</th>
                    <?php
                    foreach ($courses as $course) {
                        echo '<th>' . $course->course_name . '</th>';
                    }
                    ?>
                </tr>
                <tr>
                    <?php
                        echo '<td>' . rand(0, 100) . '</td><td>' . rand(0, 100) . '</td>';
                    ?>
                    <td></td>
                    <td>Viridian Group</td>
                    <?php
                    foreach ($courses as $course) {
                        echo '<td>' . rand(0, 100) . '%</td>';
                    }
                    ?>
                </tr>
                <tr>
                    <?php
                    echo '<td>' . rand(0, 100) . '</td><td>' . rand(0, 100) . '</td>';
                    ?>
                    <td></td>
                    <td>Energia Group</td>
                    <?php
                    foreach ($courses as $course) {
                        echo '<td>' . rand(0, 100) . '%</td>';
                    }
                    ?>
                </tr>
                <tr>
                    <?php
                    echo '<td>' . rand(0, 100) . '</td><td>' . rand(0, 100) . '</td>';
                    ?>
                    <td></td>
                    <td>PPB</td>
                    <?php
                    foreach ($courses as $course) {
                        echo '<td>' . rand(0, 100) . '%</td>';
                    }
                    ?>
                </tr>
                <tr>
                    <?php
                    echo '<td>' . rand(0, 100) . '</td><td>' . rand(0, 100) . '</td>';
                    ?>
                    <td></td>
                    <td>Power NI</td>
                    <?php
                    foreach ($courses as $course) {
                        echo '<td>' . rand(0, 100) . '%</td>';
                    }
                    ?>
                </tr>
                <tr>
                    <th><?=rand(0, 100)?></th>
                    <th><?=rand(0, 100)?></th>
                    <td></td>
                    <th>Overall</th>
                    <?php
                    foreach ($courses as $course) {
                        echo '<th>' . rand(0, 100) . '%</th>';
                    }
                    ?>
                </tr>
            </table>
        </div>
    <?php
    }
}

?>