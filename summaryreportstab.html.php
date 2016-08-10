<?php
// no direct access
defined('_JLMS_EXEC') or die('Restricted access');

class JLMS_SummaryReports_html
{

    static function showSummaryReport($rows, $courses, $parent_groups, $pageNav, $lists)
    {
        global $Itemid;
        $link = "index.php?option=com_joomla_lms&task=default&Itemid=$Itemid&activetab=jlmsTabSummaryReport";
        $app = JFactory::getApplication();
        $ug_name_id = $app->getUserStateFromRequest("plgJlmsSummaryReportsTab.ug_name", 'ug_name', '', 'cmd');
        ?>
        <script type="text/javascript">
            jQuery(function () {
                jQuery('#summary-report-download').on('click', set);
                jQuery('#summary-report-search').on('click', unset);
                jQuery('#limit_chzn').on('click', unset);
                jQuery('#summary-report-form-action #course_name').on('change', submit_filter);
                jQuery('#summary-report-form-action #ug_name').on('change', submit_filter);
                jQuery('#summary-report-form-action #category_name').on('change', submit_filter);

                function submit_filter() {
                    unset();
                    jQuery('#summary-report-form-action').submit();
                };
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
            method="post" id="summary-report-form-action" name="reportForm">

            <?php
            $filtertop = new \LMS\Widgets\Filters();
            $filterbuttom = new \LMS\Widgets\Filters();
            $filterbuttom->addFilter($pageNav->GetLimitBox($link));
            $filterbuttom->addFilter('<button class="btn tip hasTooltip" id="summary-report-download" type="submit" title="Download summary report"><i class="icon-download"></i></button>');
            $filtertop->addFilter($lists['group']);
            $filtertop->addFilter($lists['course']);
            $filtertop->addFilter($lists['category']);

            echo $filtertop;
            echo $filterbuttom;
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
                        Organisation
                    </th>
                    <th align="center">
                        Department
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
        <?php
        if (!$ug_name_id) self::showTotalTable($parent_groups, $courses, 'Overall site statistics');
        foreach ($parent_groups as $parent_group) {
            if (isset($parent_group->child_groups)) {
                self::showTotalTable($parent_group->child_groups, $courses, $parent_group->ug_name);
            }
        }

    }

    static function showTotalTable($results, $courses, $caption = '')
    {
        $total_staff = $total_excluded_staff = 0;
        foreach ($courses as $course) {
            $total_overall[$course->id] = 0;
        }
        ?>
        <div>
            <h2><?= $caption ?></h2>
            <table width="100%" cellpadding="0" cellspacing="0" border="0" class="table table-striped table-hover">
                <tr>
                    <th>Staff</th>
                    <th></th>
                    <th>Group/Course</th>
                    <?php
                    foreach ($courses as $course) {
                        echo '<th>' . $course->course_name . '</th>';
                    }
                    ?>
                </tr>
                <?php
                foreach ($results as $result) {
                    ?>
                    <tr>
                        <?php
                        $total_staff += $result->total_users;
                        $total_excluded_staff += $result->total_blocked_users;
                        $diff_total_excl = $result->total_users - $result->total_blocked_users;
                        echo '<td>' . $diff_total_excl . '</td>';
                        ?>
                        <td></td>
                        <td><?= $result->ug_name; ?></td>
                        <?php
                        foreach ($courses as $course) {
                            echo '<td>' . ($result->total[$course->id] ? round(($result->total[$course->id] / $diff_total_excl * 100)) : 0) . '%</td>';
                            $total_overall[$course->id] += $result->total[$course->id];
                        }
                        ?>
                    </tr>
                <?php
                }
                ?>

                <tr>
                    <th><?= $total_staff - $total_excluded_staff; ?></th>
                    <td></td>
                    <th>Overall</th>
                    <?php
                    $diff_total_excl = $total_staff - $total_excluded_staff;
                    foreach ($courses as $course) {
                        echo '<th>' . ($total_overall[$course->id] ? (round($total_overall[$course->id] / $diff_total_excl * 100)) : 0) . '%</th>';
                    }
                    ?>
                </tr>
            </table>
        </div>
    <?php
    }
}

?>