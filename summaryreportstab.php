<?php
defined('_JEXEC') or die('Restricted access');

require_once("summaryreportstab.html.php");

class plgJlmsSummaryReportsTab extends JPlugin
{
    protected $autoloadLanguage = true;

    public function __construct(&$subject, $config = array())
    {
        parent::__construct($subject, $config);
    }

    /***
     * Run trigger to show tab
     */
    public function onRenderTabHomepage()
    {
        $JLMS_ACL = JLMSFactory::getACL();
        if ($JLMS_ACL->isAdmin()) {
            $titleTab = $this->params->get('title_tab', 'Summary Report');
            echo JHtml::_('bootstrap.addTab', 'JLMS', 'jlmsTabSummaryReport', JText::_($titleTab, true));
            self::JLMS_SummaryReportScorm();
            echo JHtml::_('bootstrap.endTab');
        }
    }

    /***
     * Generate and collect data for summary report tab
     *
     * @throws Exception
     */
    public function JLMS_SummaryReportScorm()
    {
        $app = JFactory::getApplication();
        $db = JFactory::getDbo();
        $lists = [];

        //Start:Summary data
        $ug_name_id = $app->getUserStateFromRequest("plgJlmsSummaryReportsTab.ug_name", 'ug_name', '', 'cmd');
        $course_name_id = $app->getUserStateFromRequest("plgJlmsSummaryReportsTab.course_name", 'course_name', '', 'cmd');

        $lists['group'] = mosHTML::selectList(self::getGroups(true), 'ug_name', 'class="inputbox" size="1" ', 'id', 'ug_name', $ug_name_id);;
        $lists['course'] = mosHTML::selectList(self::getCourses(true), 'course_name', 'class="inputbox" size="1" ', 'id', 'course_name', $course_name_id);;

        $query = $db->getQuery(true);
        $query->select('u.username, u.name, u.id, ug.ug_name, sug.ug_name AS `subgroup_name`, mu.name AS `manager_name`')
            ->from('#__users AS u')
            ->innerJoin('#__lms_users_in_groups AS g ON g.user_id = u.id')
            ->leftJoin('#__lms_users_in_global_groups AS gg ON gg.user_id = g.user_id')
            ->leftJoin('#__lms_usergroups AS ug ON ug.id = gg.group_id')
            ->leftJoin('#__lms_usergroups AS sug ON sug.id = gg.subgroup1_id')
            ->leftJoin('#__lms_user_parents AS p ON p.user_id = u.id')
            ->leftJoin('#__users AS mu ON mu.id = p.parent_id')
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
                    ->andWhere('cer.crt_option=1')
                    //TODO filters by course
                ;
                $db->setQuery($query);
                $user->completed_courses = $db->loadColumn();
            }
        //echo '<pre>'; print_R($users);  echo '</pre>';

        $limit = intval($app->getUserStateFromRequest("plgJlmsSummaryReportsTab.limit", 'limit', $app->getCfg('list_limit')));
        $limitstart = intval($app->getUserStateFromRequest("plgJlmsSummaryReportsTab.limitstart", 'limitstart', 0));

        $pageNav = new JPagination(count($users), $limitstart, $limit);
        $pageNav->setAdditionalUrlParam('activetab', 'jlmsTabSummaryReport');

        $courses = self::getCourses();

        //Start:Total data
        $parent_groups = self::getGroups(false, true);
        foreach ($parent_groups as $parent_group)
        {
            /*
            $in = [$parent_group->id];
            $child_groups = self::getGroups(false, false, $parent_group->id);
            if (sizeof($child_groups))
            {
                foreach ($child_groups as $child_group) {
                    $in[] = $child_group->id;
                }

            }*/
            $query = $db->getQuery(true);
            $query->select('u.id,u.block')
                ->from('#__lms_users_in_global_groups AS gg')
                ->innerJoin('#__users AS u ON u.id=gg.user_id')
                ->where('gg.group_id='.$parent_group->id);
            $db->setQuery($query);
            $group_data = $db->loadObjectList();
            $parent_group->total_users = count($group_data);

            $blocked_count = 0;
            $active_users = [];
            foreach ($group_data as $user) {
                if($user->block==1)
                {
                    $blocked_count++;
                }else{
                    $active_users[] = $user->id;
                }
            }
            $parent_group->total_blocked_users = $blocked_count;

            $parent_group->total_completed = 0;
            if (sizeof($active_users))
            {
                $query = $db->getQuery(true);
                $query->select('COUNT(id)')
                    ->from('#__lms_certificate_users AS cer')
                    ->where('cer.user_id IN (' . implode(',',$active_users).')')
                    ->andWhere('cer.crt_option=1')
                    //TODO filters by course
                ;
                $db->setQuery($query);
                $parent_group->total_completed = $db->loadResult();
            }

        }
        echo '<pre>'; print_R($parent_groups);  echo '</pre>';

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
     * @param bool $only_parents
     * @param bool $parent_id
     * @return array
     */
    public function getGroups($with_default_item = false, $only_parents = false, $parent_id = false)
    {
        global $JLMS_DB;

        $query = $JLMS_DB->getQuery(true);
        $query->select('ug_name,id')->from('#__lms_usergroups');
        if ($only_parents)
        {
            $query->where('parent_id=0');
        }
        if ($parent_id)
        {
            $query->where('parent_id='.$parent_id);
        }
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

    /***
     * Run render excel XLSX file
     *
     * @param $users
     * @param $courses
     */
    public function renderExcel($users, $courses)
    {
        require_once("excel.php");
        Excel::init();
        $objPHPExcel = new \PHPExcel();

        $first_letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $users_count = count($users);
        $courses_count = count($courses);

        $objPHPExcel->getProperties()->setCreator("Max Stemplevski")
            ->setLastModifiedBy("Max Stemplevski")
            ->setTitle("JLMS Test Document")
            ->setSubject("Coyle JLMS Test Document")
            ->setDescription("Coyle JLMS Test document for PHPExcel, generated using PHP classes.")
            ->setKeywords("office Coyle users results")
            ->setCategory("Coyle test result file");

        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', 'Ref')
            ->setCellValue('B1', 'Firstname Lastname');
        $active_letter_index = 1;
        foreach ($courses as $course) {
            $active_letter_index++;
            $active_letter = $first_letters[$active_letter_index];
            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue($active_letter . '1', $course->course_name);
        }
        $active_letter_index++;
        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue($first_letters[$active_letter_index++] . '1', 'Department')
            ->setCellValue($first_letters[$active_letter_index++] . '1', 'Organisation')
            //->setCellValue($first_letters[$active_letter_index++] . '1', 'MD')
            ->setCellValue($first_letters[$active_letter_index++] . '1', 'Senior Manager')
            //->setCellValue($first_letters[$active_letter_index++] . '1', 'Business')
            ->setCellValue($first_letters[$active_letter_index++] . '1', 'Comment')
            //->setCellValue($first_letters[$active_letter_index++] . '1', 'Lookup Table')
            //->setCellValue($first_letters[$active_letter_index++] . '1', 'All Courses Complete')
            //->setCellValue($first_letters[$active_letter_index++] . '1', 'Include in Totals calcs')
        ;
        $objPHPExcel->getActiveSheet()->getStyle('A1:' . $first_letters[$active_letter_index] . '1')->getFont()->setBold(true);

        for ($i = 0; $i < $active_letter_index; $i++) {
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
                $userdata[] = (in_array($course->id, $completed_courses) ? 'Y' : 'N');
            }
            $userdata[] = $user->ug_name;
            $userdata[] = $user->subgroup_name;
            //$userdata[] = '';
            $userdata[] = $user->manager_name;
            $dataArray[] = $userdata;
        }

        $objPHPExcel->getActiveSheet()->fromArray($dataArray, NULL, 'A2');
        $objPHPExcel->getActiveSheet()->setAutoFilter($objPHPExcel->getActiveSheet()->calculateWorksheetDimension());
        $objPHPExcel->getActiveSheet()->freezePane('C2');

        $active_letter_index = 1 + $courses_count;
        $active_diapason_number = 1 + $users_count;
        $objPHPExcel->getActiveSheet()->getStyle('C2:'.$first_letters[$active_letter_index].$active_diapason_number)->getAlignment()
            ->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER)
            ->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

        //Rename worksheet
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