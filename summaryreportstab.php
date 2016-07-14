<?php
defined('_JEXEC') or die('Restricted access');

require_once("summaryreportstab.html.php");

class plgJlmsSummaryReportsTab extends JPlugin
{

    const MIN_COL_WIDTH = 18;
    protected $autoloadLanguage = true;

    public function __construct(&$subject, $config = array())
    {
        parent::__construct($subject, $config);
    }

    public function onRenderTabHomepage()
    {
        $JLMS_ACL = JLMSFactory::getACL();
        if ($JLMS_ACL->isTeacher() || $JLMS_ACL->isAdmin()) {

            $titleTab = $this->params->get('title_tab', 'Summary Report');
            echo JHtml::_('bootstrap.addTab', 'JLMS', 'jlmsTabSummaryReport', JText::_($titleTab, true));
            if ($JLMS_ACL->isAdmin()) {
                self::JLMS_SummaryReportScorm();
            }
            echo JHtml::_('bootstrap.endTab');
        }
    }

    public function JLMS_SummaryReportScorm()
    {
        $app = JFactory::getApplication();
        $start_date = $app->getUserStateFromRequest("plgJlmsSummaryReportsTab.from_date", 'from_date', null, 'cmd');
        $end_date = $app->getUserStateFromRequest("plgJlmsSummaryReportsTab.to_date", 'to_date', null, 'cmd');
        $ug_name_id = $app->getUserStateFromRequest("plgJlmsSummaryReportsTab.ug_name", 'ug_name', '', 'cmd');
        $course_name_id = $app->getUserStateFromRequest("plgJlmsSummaryReportsTab.course_name", 'course_name', '', 'cmd');

        $group_filter = mosHTML::selectList(self::getGroups(true), 'ug_name', 'class="inputbox" size="1" ', 'id', 'ug_name', $ug_name_id);
        $courses_filter = mosHTML::selectList(self::getCourses(true), 'course_name', 'class="inputbox" size="1" ', 'id', 'course_name', $course_name_id);

        $lists = array();
        $lists['group'] = $group_filter;
        $lists['course'] = $courses_filter;

        $db = JFactory::getDbo();

        $query = $db->getQuery(true);
        $query->select('u.username, u.name, u.id, ug.ug_name, sug.ug_name AS `subgroup_name`')
            ->from('#__users AS u')
            ->innerJoin('#__lms_users_in_groups AS g ON g.user_id = u.id')
            ->leftJoin('#__lms_users_in_global_groups AS gg ON gg.user_id = g.user_id')
            ->leftJoin('#__lms_usergroups AS ug ON ug.id = gg.group_id')
            ->leftJoin('#__lms_usergroups AS sug ON sug.id = gg.subgroup1_id')
            //TODO filters by course and group
            ->group('u.id')
            ->order('u.username');
        $db->setQuery($query);
        $users = $db->LoadObjectList();

        if (sizeof($users))
            foreach ($users as $user) {
                $query = $db->getQuery(true);
                $query->select('course_id')
                    ->from('#__lms_certificate_users AS cer')
                    ->where('cer.user_id=' . $user->id)
                    ->andWhere('cer.crt_option=1')//TODO filters by course
                ;
                $db->setQuery($query);
                $user->completed_courses = $db->loadColumn();
            }

        //echo '<pre>'; print_R($users);  echo '</pre>';

        $lists['compl_date'] = mosGetParam($_REQUEST, 'compl_date ', 0);
        $lists['from_date'] = JHTML::_('calendar', $start_date, 'from_date', 'from_date');
        $lists['to_date'] = JHTML::_('calendar', $end_date, 'to_date', 'to_date');

        $limit = intval($app->getUserStateFromRequest("plgJlmsSummaryReportsTab.limit", 'limit', $app->getCfg('list_limit')));
        $limitstart = intval($app->getUserStateFromRequest("plgJlmsSummaryReportsTab.limitstart", 'limitstart', 0));

        $pageNav = new JPagination(count($users), $limitstart, $limit);
        $pageNav->setAdditionalUrlParam('activetab', 'jlmsTabSummaryReport');

        $courses = self::getCourses();
        if ($app->input->getCmd('download-summary-report') == "excel") {
            self::renderExcel($users, $courses);
        } else {
            $users = array_slice($users, $pageNav->limitstart, $pageNav->limit);
            JLMS_SummaryReports_html::showSummaryReport($users, $courses, $pageNav, $lists);
        }
    }

    /***
     * Get list of groups
     *
     * @param bool $with_default_item
     * @return array
     */
    public function getGroups($with_default_item = false)
    {
        global $JLMS_DB;

        $query = $JLMS_DB->getQuery(true);
        $query->select('ug_name,id')->from('#__lms_usergroups');
        $JLMS_DB->setQuery($query);
        $groups = (array)$JLMS_DB->loadObjectList();

        if ($with_default_item) {
            $group_default = new stdClass();
            $group_default->ug_name = 'Select group';
            $group_default->id = 0;
            return array_merge([$group_default], $groups);
        }
        return $groups;
    }

    /***
     * Get list of courses
     *
     * @param bool $with_default_item
     * @return array
     */
    public function getCourses($with_default_item = false)
    {
        global $JLMS_DB;

        $query = $JLMS_DB->getQuery(true);
        $query->select('course_name,id')->from('#__lms_courses');
        $JLMS_DB->setQuery($query);
        $courses = (array)$JLMS_DB->loadObjectList();

        if ($with_default_item) {
            $course_default = new stdClass();
            $course_default->course_name = 'Select course';
            $course_default->id = 0;
            return array_merge([$course_default], $courses);
        }
        return $courses;
    }

    public function renderExcel($users,$courses)
    {
        require_once("excel.php");
        Excel::init();
        $objPHPExcel = new \PHPExcel();

        $first_letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $objPHPExcel->getProperties()->setCreator("Max Stemplevski")
            ->setLastModifiedBy("Max Stemplevski")
            ->setTitle("JLMS Test Document")
            ->setSubject("Coyle JLMS Test Document")
            ->setDescription("Coyle JLMS Test document for PHPExcel, generated using PHP classes.")
            ->setKeywords("office Coyle users results")
            ->setCategory("Coyle test result file");

        // Add some data
        //echo date('H:i:s'), " Add some data", EOL;
        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', 'Ref')
            ->setCellValue('B1', 'Firstname Lastname')
        ;
        $active_letter_index = 1;
        foreach ($courses as $course) {
            $active_letter_index++;
            $active_letter = $first_letters[$active_letter_index];
            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue($active_letter.'1', $course->course_name);
        }
        $active_letter_index++;
        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue($first_letters[$active_letter_index++].'1', 'Department')
            ->setCellValue($first_letters[$active_letter_index++].'1', 'Organisation')
            ->setCellValue($first_letters[$active_letter_index++].'1', 'MD')
            ->setCellValue($first_letters[$active_letter_index++].'1', 'Senior Manager')
            ->setCellValue($first_letters[$active_letter_index++].'1', 'Business')
            ->setCellValue($first_letters[$active_letter_index++].'1', 'Comment')
            ->setCellValue($first_letters[$active_letter_index++].'1', 'Lookup Table')
            ->setCellValue($first_letters[$active_letter_index++].'1', 'All Courses Complete')
            ->setCellValue($first_letters[$active_letter_index++].'1', 'Include in Totals calcs')
            ;

        $objPHPExcel->getActiveSheet()->getStyle('A1:Z1')->getFont()->setBold(true);

        for ($i = 0; $i < strlen($first_letters); $i++) {
            echo $first_letters[$i];
            $objPHPExcel->getActiveSheet()->getColumnDimension($first_letters[$i])->setAutoSize(true);
        }

        $dataArray = [];
        foreach ($users as $user) {
            $userdata = [];
            $userdata[] = $user->username;
            $userdata[] = $user->name;
            foreach ($courses as $course) {
                $completed_courses = $user->completed_courses;
                $userdata[] = (in_array($course->id,$completed_courses)?'Y':'N');
            }
            $userdata[] = $user->ug_name;
            $userdata[] = $user->subgroup_name;
            $userdata[] = '';
            $userdata[] = $user->name;
            $dataArray[] = $userdata;
        }

        $objPHPExcel->getActiveSheet()->fromArray($dataArray, NULL, 'A2');
        $objPHPExcel->getActiveSheet()->setAutoFilter($objPHPExcel->getActiveSheet()->calculateWorksheetDimension());
        $objPHPExcel->getActiveSheet()->freezePane('D2');

        // Rename worksheet
        echo date('H:i:s'), " Rename worksheet", EOL;
        $objPHPExcel->getActiveSheet()->setTitle('Summary');

        /***2ND worksheet start**/
        $totalSheet = new PHPExcel_Worksheet($objPHPExcel, 'Totals');
        $objPHPExcel->addSheet($totalSheet, 1);
        $objPHPExcel->setActiveSheetIndex(1);
        $objPHPExcel->getActiveSheet()->setCellValue('E1', 'Status of Online Training');
        $objPHPExcel->getActiveSheet()->getStyle('A1:Z1')->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getColumnDimension('E1')->setAutoSize(true);

        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $objPHPExcel->setActiveSheetIndex(0);

        ob_end_clean();
        // Redirect output to a client web browser (Excel2007)
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename = Report for summary.xlsx');
        header('Cache-Control: max-age=0');

        // Save Excel 2007 file
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->setIncludeCharts(TRUE);
        $objWriter->save('php://output');
        die;
    }

}