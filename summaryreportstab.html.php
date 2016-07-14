<?php
// no direct access
defined( '_JLMS_EXEC' ) or die( 'Restricted access' );

class JLMS_SummaryReports_html {

    static function showSummaryReport($rows, $courses, $pageNav, $lists) {
        global $Itemid;
        ?>
        <script type="text/javascript">
            jQuery(function(){
                jQuery('#summary-report-download').on('click', set);
                jQuery('#summary-report-search').on('click', unset);
                function set(){
                    jQuery('#download-summary-report').attr('value','excel');
                };
                function unset(){
                    jQuery('#download-summary-report').attr('value', '');
                };

            });
        </script>
        <form action="<?php echo sefRelToAbs("index.php?option=com_joomla_lms&task=default&Itemid=$Itemid&activetab=jlmsTabSummaryReport");?>" method="post" id="report-form-action" name="reportForm">

            <?php
            $filtertop = new \LMS\Widgets\Filters();
            $filterbuttom = new \LMS\Widgets\Filters();
            $pagenavlink = "index.php?option=com_joomla_lms&amp;task=default&amp;Itemid=$Itemid&amp;&activetab=jlmsTabSummarysummaryreport";

            $limitBox = $pageNav->GetLimitBox( $pagenavlink );
            $filtertop->addFilter($limitBox);
            $filterbuttom->addFilter('<button class="btn tip hasTooltip" id="summary-report-search" type="submit" title="'.JText::_('JSEARCH_FILTER_SUBMIT').'"><i class="icon-search"></i></button>');
            $filterbuttom->addFilter('<button class="btn tip hasTooltip" id="summary-report-download" type="submit" title="Download summary report"><i class="icon-download"></i></button>');
            $filtertop->addFilter($lists['group']);
            $filtertop->addFilter($lists['course']);
            echo $filtertop;
            echo $filterbuttom;
            ?>

            <table width="100%" cellpadding="0" cellspacing="0" border="0" class="table table-striped">
                <thead>
                <tr>
                    <td align="center">
                        <?php echo ('Ref')?><br>
                        <?php echo ('Firstname Lastname')?>
                    </td>
                    <td align="center">
                        <?php echo ('Courses');	?>
                    </td>
                    <td align="center">
                        <?php echo ('Department');	?>
                    </td>
                    <td align="center">
                        <?php echo ('Organisation');?>
                    </td>
                    <td align="center">
                        <?php echo ('Senior Manager');?>
                    </td>
                </tr>
                </thead>
                <?php
                foreach ($rows as $row) {
                    ?>
                    <tr>
                        <td>
                            <?php echo $row->username;?>
                            <br>
                            <?php echo $row->name;?>
                        </td>
                        <td nowrap="nowrap">
                            <?php
                            $completed_courses = $row->completed_courses;
                            foreach ($courses as $course) {
                                echo $course->course_name.': '.(in_array($course->id,$completed_courses)?'Yes':'No').'<br/>';
                            }
                            ?>
                        </td>
                        <td align= "center">
                            <?php echo $row->ug_name;?>
                        </td>
                        <td align= "center">
                            <?php echo $row->subgroup_name;?>
                        </td>
                        <td align= "center">
                            <?php echo $row->name;?>
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
                                <?php echo $pageNav->getListFooter();?>
                            </td>
                        </div>
                    </div>
                </tr>
                </tfoot>
            </table>
            <input type="hidden" id ="download-summary-report" name="download-summary-report" value="" />
        </form>
    <?php
    }
}
?>