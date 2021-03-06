<?php
defined('_JEXEC') or die('Restricted access');

require_once("summaryreportstab.html.php");

class plgJlmsSummaryReportsTab extends JPlugin
{
    protected $autoloadLanguage = true;
    static private $allowed_courses_ids = [];
    static private $only_admin_groups = [];
    static private $only_seo_users = [];

    public function __construct(&$subject, $config = [])
    {
        $plugin = JPluginHelper::getPlugin('jlms', 'summaryreportstab');
        $params = json_decode($plugin->params);
        $param_courses_ids = $params->courses_ids;
        if ($param_courses_ids) {
            self::$allowed_courses_ids = explode(',', $param_courses_ids);
        }

        parent::__construct($subject, $config);
    }

    /***
     * Run trigger to show tab
     */
    public function onRenderTabHomepage()
    {
        $JLMS_ACL = JLMSFactory::getACL();

        if ($JLMS_ACL->isAdmin() || $JLMS_ACL->_role_type == 3 || $JLMS_ACL->_role_type == 4) {
            $titleTab = $this->params->get('title_tab', 'Summary Report');
            echo JHtml::_('bootstrap.addTab', 'JLMS', 'jlmsTabSummaryReport', JText::_($titleTab, true));
            if (sizeof(self::$allowed_courses_ids)) {
                self::JLMS_SummaryReportScorm();
            } else {
                echo '<strong>For generating summary report, you need to add courses ids in plugin params, please.</strong>';
            }
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
        global $JLMS_CONFIG;
        $JLMS_ACL = JLMSFactory::getACL();
        $app = JFactory::getApplication();
        $db = JFactory::getDbo();
        $user = JFactory::getUser();
        $lists = $only_in_groups = [];

        //Start:Summary data
        $ug_name_id = $app->getUserStateFromRequest("plgJlmsSummaryReportsTab.ug_name", 'ug_name', '', 'cmd');
        $course_name_id = $app->getUserStateFromRequest("plgJlmsSummaryReportsTab.course_name", 'course_name', '', 'cmd');
        $category_name_id = $app->getUserStateFromRequest("plgJlmsSummaryReportsTab.category_name", 'category_name', '', 'cmd');

        if ($JLMS_ACL->isAdmin() && $JLMS_CONFIG->get('use_global_groups', 1) && $JLMS_ACL->CheckPermissions('advanced', 'assigned_groups_only')) {
            $query = $db->getQuery(true);
            $query->select('lu.id')
                ->from('#__lms_usergroups AS lu')
                ->innerJoin('#__lms_user_assign_groups AS luag ON luag.group_id =  lu.id')
                ->where("luag.user_id = " . $user->id);
            $db->setQuery($query);
            $only_in_groups = $db->loadColumn();
            self::$only_admin_groups = $only_in_groups;
        }

        if ($JLMS_ACL->_role_type == 3 || $JLMS_ACL->_role_type == 4) {
            $query = $db->getQuery(true);
            $query->select('lu.id')
                ->from('#__lms_usergroups AS lu')
                ->innerJoin('#__lms_user_assign_groups AS luag ON luag.group_id =  lu.id')
                ->where("luag.user_id = " . $user->id);
            $db->setQuery($query);
            $only_in_groups = $db->loadColumn();
            self::$only_admin_groups = $only_in_groups;
            if (!sizeof(self::$only_admin_groups)) {
                $query = $db->getQuery(true);
                $query->select('p.user_id')
                    ->from('#__lms_user_parents AS p')
                    ->where("p.parent_id = " . $user->id);
                $db->setQuery($query);
                $only_users = $db->loadColumn();
                self::$only_seo_users = $only_users;
            }
        }

        $lists['group'] = (sizeof(self::$only_seo_users)) ? '' : mosHTML::selectList(self::getGroups(true, true), 'ug_name', 'class="inputbox" size="1" ', 'id', 'ug_name', $ug_name_id);
        $lists['course'] = mosHTML::selectList(self::getCourses(true), 'course_name', 'class="inputbox" size="1" ', 'id', 'course_name', $course_name_id);
        $lists['category'] = mosHTML::selectList(self::getCategories(true), 'category_name', 'class="inputbox" size="1" ', 'id', 'category_name', $category_name_id);

        $query = $db->getQuery(true);
        $query->select('u.username, u.name, u.id, ug.ug_name, sug.ug_name AS `subgroup_name`, mu.name AS `manager_name`')
            ->from('#__users AS u')
            ->innerJoin('#__lms_users_in_groups AS g ON g.user_id = u.id')
            ->leftJoin('#__lms_users_in_global_groups AS gg ON gg.user_id = g.user_id')
            ->leftJoin('#__lms_usergroups AS ug ON ug.id = gg.group_id')
            ->leftJoin('#__lms_usergroups AS sug ON sug.id = gg.subgroup1_id')
            ->leftJoin('#__lms_user_parents AS p ON p.user_id = u.id')
            ->leftJoin('#__users AS mu ON mu.id = p.parent_id')
            ->group('u.id')
            ->order('u.username');
        if ($ug_name_id) {
            $query->where('gg.group_id=' . $ug_name_id);
        }
        if (sizeof(self::$only_admin_groups)) {
            $query->where('gg.group_id IN (' . implode(',', self::$only_admin_groups) . ')');
        }
        if (sizeof(self::$only_seo_users)) {
            $query->where('u.id IN (' . implode(',', self::$only_seo_users) . ')');
        }
        $query->where('u.block=0');

        $db->setQuery($query);
        $users = $db->LoadObjectList();

        if (sizeof($users))
            foreach ($users as $user) {
                $query = $db->getQuery(true);
                $query->select('course_id')
                    ->from('#__lms_certificate_users AS cer')
                    ->where('cer.user_id=' . $user->id)
                    ->where('cer.crt_option=1')
                    ->where('course_id IN (' . implode(',', self::$allowed_courses_ids) . ')');
                if ($course_name_id) {
                    $query->where('course_id=' . $course_name_id);
                }
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
        $groups_results = self::getGroups(false, true);
        foreach ($groups_results as $groups_result) {
            $query = $db->getQuery(true);
            $query->select('u.id,u.block')
                ->from('#__lms_users_in_global_groups AS gg')
                ->innerJoin('#__users AS u ON u.id=gg.user_id')
                ->where('gg.group_id=' . $groups_result->id);
            $db->setQuery($query);
            $group_data = $db->loadObjectList();
            $groups_result->total_users = count($group_data);

            $blocked_count = 0;
            $active_users = [];
            foreach ($group_data as $user) {
                if ($user->block == 1) {
                    $blocked_count++;
                } else {
                    $active_users[] = $user->id;
                }
            }
            $groups_result->total_blocked_users = $blocked_count;

            $groups_result->total_completed = 0;
            foreach ($courses as $course) {
                $groups_result->total[$course->id] = 0;
            }

            if (sizeof($active_users)) {
                $query = $db->getQuery(true);
                $query->select('COUNT(id)')
                    ->from('#__lms_certificate_users AS cer')
                    ->where('cer.user_id IN (' . implode(',', $active_users) . ')')
                    ->where('cer.crt_option=1')//TODO filters by course
                ;
                $db->setQuery($query);
                $groups_result->total_completed = $db->loadResult();

                foreach ($courses as $course) {
                    $query = $db->getQuery(true);
                    $query->select('COUNT(id)')
                        ->from('#__lms_certificate_users AS cer')
                        ->where('cer.user_id IN (' . implode(',', $active_users) . ')')
                        ->where('cer.course_id=' . $course->id)
                        ->where('cer.crt_option=1')//TODO filters by course
                    ;
                    $db->setQuery($query);
                    $groups_result->total[$course->id] = $db->loadResult();
                }
            }

            $child_groups = self::getGroups(false, false, $groups_result->id);
            if (sizeof($child_groups)) {
                foreach ($child_groups as $child_group) {
                    $query = $db->getQuery(true);
                    $query->select('u.id,u.block')
                        ->from('#__lms_users_in_global_groups AS gg')
                        ->innerJoin('#__users AS u ON u.id=gg.user_id')
                        ->where('gg.subgroup1_id=' . $child_group->id);
                    $db->setQuery($query);
                    $group_data = $db->loadObjectList();
                    $child_group->total_users = count($group_data);

                    $blocked_count = 0;
                    $active_users = [];
                    foreach ($group_data as $user) {
                        if ($user->block == 1) {
                            $blocked_count++;
                        } else {
                            $active_users[] = $user->id;
                        }
                    }
                    $child_group->total_blocked_users = $blocked_count;

                    $child_group->total_completed = 0;
                    foreach ($courses as $course) {
                        $child_group->total[$course->id] = 0;
                    }

                    if (sizeof($active_users)) {
                        $query = $db->getQuery(true);
                        $query->select('COUNT(id)')
                            ->from('#__lms_certificate_users AS cer')
                            ->where('cer.user_id IN (' . implode(',', $active_users) . ')')
                            ->where('cer.crt_option=1')//TODO filters by course
                        ;
                        $db->setQuery($query);
                        $child_group->total_completed = $db->loadResult();

                        foreach ($courses as $course) {
                            $query = $db->getQuery(true);
                            $query->select('COUNT(id)')
                                ->from('#__lms_certificate_users AS cer')
                                ->where('cer.user_id IN (' . implode(',', $active_users) . ')')
                                ->where('cer.course_id=' . $course->id)
                                ->where('cer.crt_option=1')//TODO filters by course
                            ;
                            $db->setQuery($query);
                            $child_group->total[$course->id] = $db->loadResult();
                        }
                    }
                }
                $groups_result->child_groups = $child_groups;

            }

        }

        if ($app->input->getCmd('download-summary-report') == "excel") {
            self::renderExcel($users, $courses, $groups_results);
        } else {
            $users = array_slice($users, $pageNav->limitstart, $pageNav->limit);
            JLMS_SummaryReports_html::showSummaryReport($users, $courses, $groups_results, $pageNav, $lists);
        }
    }

    /***
     * Get list of groups
     *
     * @param bool $with_default_item
     * @param bool $only_parents
     * @param bool $parent_id
     *
     * @return array
     */
    public function getGroups($with_default_item = false, $only_parents = false, $parent_id = false)
    {
        global $JLMS_DB;
        $JLMS_ACL = JLMSFactory::getACL();
        $app = JFactory::getApplication();
        $ug_name_id = $app->getUserStateFromRequest("plgJlmsSummaryReportsTab.ug_name", 'ug_name', '', 'cmd');

        $query = $JLMS_DB->getQuery(true);
        $query->select('ug_name,id')->from('#__lms_usergroups');
        if ($only_parents) {
            $query->where('parent_id=0');
        }
        if ($parent_id) {
            $query->where('parent_id=' . $parent_id);
        } else {
            if (sizeof(self::$only_admin_groups)) {
                $query->where('id IN (' . implode(',', self::$only_admin_groups) . ')');
            } else if (sizeof(self::$only_seo_users)) {
                $query->where('id=0'); //skip
            } else if (!$JLMS_ACL->isAdmin()) {
                $query->where('id=0'); //skip
            }
        }
        if ($ug_name_id && !$with_default_item && !$parent_id) {
            $query->where('id=' . $ug_name_id);
        }
        $JLMS_DB->setQuery($query);
        $groups = (array)$JLMS_DB->loadObjectList();

        if ($with_default_item) {
            $group_default = new stdClass();
            $group_default->ug_name = 'Filter by organization';
            $group_default->id = 0;
            return array_merge([$group_default], $groups);
        }
        return $groups;
    }

    /***
     * Get list of courses
     *
     * @param bool $with_default_item
     *
     * @return array
     */
    public function getCourses($with_default_item = false)
    {
        global $JLMS_DB;

        $app = JFactory::getApplication();
        $course_name_id = $app->getUserStateFromRequest("plgJlmsSummaryReportsTab.course_name", 'course_name', '', 'cmd');

        $category_name_id = $app->getUserStateFromRequest("plgJlmsSummaryReportsTab.category_name", 'category_name', '', 'cmd');

        $query = $JLMS_DB->getQuery(true);
        $query->select('course_name,id')->from('#__lms_courses')->where('id IN (' . implode(',', self::$allowed_courses_ids) . ')');
        if ($course_name_id && !$with_default_item) {
            $query->where('id=' . $course_name_id);
        }
        if ($category_name_id) {
            $query->where('cat_id=' . $category_name_id);
        }
        $JLMS_DB->setQuery($query);
        $courses = (array)$JLMS_DB->loadObjectList();

        if (sizeof($courses)) {
            $acids = self::$allowed_courses_ids;
            usort($courses, function ($a, $b) use ($acids) {
                $pos_a = array_search($a->id, $acids);
                $pos_b = array_search($b->id, $acids);
                return $pos_a - $pos_b;
            });

        }
        if ($with_default_item) {
            $course_default = new stdClass();
            $course_default->course_name = 'Filter by course';
            $course_default->id = 0;
            return array_merge([$course_default], $courses);
        }
        return $courses;
    }

    /**
     * Get LMS categories
     *
     * @param bool $with_default_item
     *
     * @return array
     * @throws Exception
     */
    public function getCategories($with_default_item = false)
    {
        global $JLMS_DB;

        $app = JFactory::getApplication();
        $category_name_id = $app->getUserStateFromRequest("plgJlmsSummaryReportsTab.category_name", 'category_name', '', 'cmd');

        $query = $JLMS_DB->getQuery(true);
        $query->select('c_category AS `category_name`,id')
            ->from('#__lms_course_cats')
            ->where('restricted=0')
            ->where('id in (SELECT DISTINCT(`cat_id`) FROM `#__lms_courses` WHERE id IN (' . implode(',', self::$allowed_courses_ids) . '))');
        if ($category_name_id && !$with_default_item) {
            $query->where('id=' . $category_name_id);
        }
        $JLMS_DB->setQuery($query);
        $categories = (array)$JLMS_DB->loadObjectList();

        if ($with_default_item) {
            $course_default = new stdClass();
            $course_default->category_name = 'Filter by category';
            $course_default->id = 0;
            return array_merge([$course_default], $categories);
        }
        return $categories;
    }

    /***
     * Run render excel XLSX file
     *
     * @param $users
     * @param $courses
     */
    public function renderExcel($users, $courses, $groups_results)
    {
        require_once("excel.php");
        Excel::init();
        $objPHPExcel = new \PHPExcel();

        $first_letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $users_count = count($users);
        $courses_count = count($courses);

        $objPHPExcel->getProperties()->setCreator("Coyle")
            ->setLastModifiedBy("Coyle")
            ->setTitle("Coyle JLMS Document")
            ->setSubject("Coyle JLMS Document")
            ->setDescription("Coyle JLMS document")
            ->setKeywords("office Coyle users results")
            ->setCategory("Coyle result file");

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
            ->setCellValue($first_letters[$active_letter_index++] . '1', 'Organisation')
            ->setCellValue($first_letters[$active_letter_index++] . '1', 'Department')
            //->setCellValue($first_letters[$active_letter_index++] . '1', 'MD')
            ->setCellValue($first_letters[$active_letter_index++] . '1', 'Senior Manager')
            //->setCellValue($first_letters[$active_letter_index++] . '1', 'Business')
            ->setCellValue($first_letters[$active_letter_index++] . '1', 'Comment')
            //->setCellValue($first_letters[$active_letter_index++] . '1', 'Lookup Table')
            //->setCellValue($first_letters[$active_letter_index++] . '1', 'All Courses Complete')
            //->setCellValue($first_letters[$active_letter_index++] . '1', 'Include in Totals calcs')
        ;
        $objPHPExcel->getActiveSheet()->getStyle('A1:' . $first_letters[$active_letter_index] . '1')->getFont()->setBold(true);
        $styleFirsetline = [
            'fill' => [
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => ['argb' => 'FFFF00']
            ]
        ];
        $objPHPExcel->getActiveSheet()->getStyle('A1:' . $first_letters[$active_letter_index] . '1')->applyFromArray($styleFirsetline);

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
        $objPHPExcel->getActiveSheet()->getStyle('C2:' . $first_letters[$active_letter_index] . $active_diapason_number)->getAlignment()
            ->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER)
            ->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

        //Rename worksheet
        $objPHPExcel->getActiveSheet()->setTitle('Summary');

        /***2ND worksheet start**/
        $totalSheet = new PHPExcel_Worksheet($objPHPExcel, 'Totals');
        $objPHPExcel->addSheet($totalSheet, 1);
        $objPHPExcel->setActiveSheetIndex(1);

        $last_allowed_letter_index = count($courses) + 5;
        $last_allowed_letter = $first_letters[$last_allowed_letter_index];

        //start styles
        $styleThickBrownBorderOutline = [
            'borders' => [
                'outline' => [
                    'style' => PHPExcel_Style_Border::BORDER_THICK,
                    'color' => ['argb' => '000000'],
                ],
                'allborders' => [
                    'style' => PHPExcel_Style_Border::BORDER_THIN
                ]
            ],
        ];

        $styleCourseResultsBorder = [
            'borders' => [
                'allborders' => [
                    'style' => PHPExcel_Style_Border::BORDER_DASHED,
                    'color' => ['argb' => '000000'],
                ],
                'right' => [
                    'style' => PHPExcel_Style_Border::BORDER_THICK,
                    'color' => ['argb' => '000000'],
                ],
            ],
        ];

        $styleCoursesHeader = [
            'fill' => [
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => ['argb' => 'FFFF00']
            ]
        ];

        $objPHPExcel->getActiveSheet()->getStyle('A1:' . $last_allowed_letter . '1')->applyFromArray(
            ['fill' => [
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => ['argb' => '9370BD']
            ]
            ]
        );

        $objPHPExcel->getActiveSheet()->getStyle('A2:' . $last_allowed_letter . (count($groups_results) * 10))->applyFromArray(
            ['fill' => [
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => ['argb' => 'FFFFFF']
            ],
                'borders' => []
            ]
        );

        $objPHPExcel->getActiveSheet()->getStyle('A2:' . $last_allowed_letter . '500')->getAlignment()->setWrapText(true);

        //conditionals
        $objConditional_minus_staff = new PHPExcel_Style_Conditional();
        $objConditional_minus_staff->setConditionType(PHPExcel_Style_Conditional::CONDITION_CELLIS)
            ->setOperatorType(PHPExcel_Style_Conditional::OPERATOR_GREATERTHAN)
            ->addCondition('0');
        $objConditional_minus_staff->getStyle()->getFont()->getColor()->setARGB(PHPExcel_Style_Color::COLOR_DARKRED);
        //$objConditional_minus_staff->getStyle()->getFont()->setBold(true);
        $objConditional_minus_staff->getStyle()->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getEndColor()->setARGB('FFC7CE');

        $objConditional_course_completed = new PHPExcel_Style_Conditional();
        $objConditional_course_completed->setConditionType(PHPExcel_Style_Conditional::CONDITION_CELLIS)
            ->setOperatorType(PHPExcel_Style_Conditional::OPERATOR_EQUAL)
            ->setCondition('1');
        $objConditional_course_completed->getStyle()->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_PERCENTAGE);
        $objConditional_course_completed->getStyle()->getFont()->getColor()->setARGB(PHPExcel_Style_Color::COLOR_DARKGREEN);
        //$objConditional_course_completed->getStyle()->getFont()->setBold(true);
        $objConditional_course_completed->getStyle()->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getEndColor()->setARGB('C6EFCE');


        $objPHPExcel->getActiveSheet()->setCellValue('E1', 'Status of Online Training');
        $objPHPExcel->getActiveSheet()->getStyle('E1')->getFont()->setName('Calibri');
        $objPHPExcel->getActiveSheet()->getStyle('E1')->getFont()->setSize(14);
        $objPHPExcel->getActiveSheet()->getStyle('E1')->getFont()->setBold(true);

        $objPHPExcel->getActiveSheet()->getColumnDimension('E1')->setAutoSize(true);


        $dataArray = self::generateTotalTable($groups_results, $courses, 'Overall site statistics');

        $lfai = 1; //B
        $fai = 3;
        $lai = $fai + 1;
        $llai = count($courses) + 4;
        $objPHPExcel->getActiveSheet()->getStyle($first_letters[$lfai] . $fai . ':' . $first_letters[$llai] . $lai)->getFont()->setBold(true);

        //courses result position
        $objPHPExcel->getActiveSheet()->getStyle($first_letters[$lfai + 4] . ($fai + 2) . ':' . $first_letters[$llai] . ($lai + count($groups_results) + 1))->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);

        //course completed condition
        $conditionalStyles = $objPHPExcel->getActiveSheet()->getStyle($first_letters[$lfai + 4] . ($fai + 2) . ':' . $first_letters[$llai] . ($lai + count($groups_results) + 1))->getConditionalStyles();
        array_push($conditionalStyles, $objConditional_course_completed);
        $objPHPExcel->getActiveSheet()->getStyle($first_letters[$lfai + 4] . ($fai + 2) . ':' . $first_letters[$llai] . ($lai + count($groups_results) + 1))->setConditionalStyles($conditionalStyles);

        //percent_format
        $objPHPExcel->getActiveSheet()->getStyle($first_letters[$lfai + 4] . ($fai + 2) . ':' . $first_letters[$llai] . ($lai + count($groups_results) + 1))
            ->getNumberFormat()->applyFromArray(
                [
                    'code' => PHPExcel_Style_NumberFormat::FORMAT_PERCENTAGE
                ]
            );

        //courses header color
        $objPHPExcel->getActiveSheet()->getStyle($first_letters[$lfai + 4] . ($fai + 1) . ':' . $first_letters[$llai] . ($fai + 1))->applyFromArray($styleCoursesHeader);

        //courses stat border
        $objPHPExcel->getActiveSheet()->getStyle($first_letters[$lfai + 3] . ($fai + 1) . ':' . $first_letters[$llai] . ($lai + count($groups_results) + 1))->applyFromArray($styleThickBrownBorderOutline);

        //courses bottom dashed
        $objPHPExcel->getActiveSheet()->getStyle($first_letters[$lfai + 4] . ($fai + 2) . ':' . $first_letters[$llai] . ($lai + count($groups_results)))->applyFromArray($styleCourseResultsBorder);

        //staff border
        $objPHPExcel->getActiveSheet()->getStyle($first_letters[$lfai] . ($fai + 2) . ':' . $first_letters[$lfai] . ($lai + count($groups_results)))->applyFromArray($styleThickBrownBorderOutline);

        //staff conditional
        $conditionalStyles = $objPHPExcel->getActiveSheet()->getStyle($first_letters[$lfai + 1] . ($fai + 2) . ':' . $first_letters[$lfai + 1] . ($lai + count($groups_results)))->getConditionalStyles();
        array_push($conditionalStyles, $objConditional_minus_staff);
        $objPHPExcel->getActiveSheet()->getStyle($first_letters[$lfai + 1] . ($fai + 2) . ':' . $first_letters[$lfai + 1] . ($lai + count($groups_results)))->setConditionalStyles($conditionalStyles);

        //staff center
        $objPHPExcel->getActiveSheet()->getStyle($first_letters[$lfai] . ($fai + 1) . ':' . $first_letters[$lfai + 1] . ($lai + count($groups_results) + 1))->getAlignment()
            ->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER)
            ->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

        $dataArray[] = array_merge($dataArray, []);

        $fai = $lai + count($groups_results) + 1;
        $lai = $fai + 3;
        $objPHPExcel->getActiveSheet()->getStyle($first_letters[$lfai] . $fai . ':' . $first_letters[$llai] . $lai)->getFont()->setBold(true);

        foreach ($groups_results as $parent_group) {
            if (isset($parent_group->child_groups)) {
                $dataArray = array_merge($dataArray, self::generateTotalTable($parent_group->child_groups, $courses, $parent_group->ug_name));
                $dataArray[] = array_merge($dataArray, []);

                //courses result position
                $objPHPExcel->getActiveSheet()->getStyle($first_letters[$lfai + 4] . ($fai + 4) . ':' . $first_letters[$llai] . ($lai + count($parent_group->child_groups) + 1))->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);

                //percent_format
                $objPHPExcel->getActiveSheet()->getStyle($first_letters[$lfai + 4] . ($fai + 4) . ':' . $first_letters[$llai] . ($lai + count($parent_group->child_groups) + 1))
                    ->getNumberFormat()->applyFromArray(
                        [
                            'code' => PHPExcel_Style_NumberFormat::FORMAT_PERCENTAGE
                        ]
                    );

                //course completed condition
                $conditionalStyles = $objPHPExcel->getActiveSheet()->getStyle($first_letters[$lfai + 4] . ($fai + 4) . ':' . $first_letters[$llai] . ($lai + count($parent_group->child_groups) + 1))->getConditionalStyles();
                array_push($conditionalStyles, $objConditional_course_completed);
                $objPHPExcel->getActiveSheet()->getStyle($first_letters[$lfai + 4] . ($fai + 4) . ':' . $first_letters[$llai] . ($lai + count($parent_group->child_groups) + 1))->setConditionalStyles($conditionalStyles);

                //courses header color
                $objPHPExcel->getActiveSheet()->getStyle($first_letters[$lfai + 4] . ($fai + 3) . ':' . $first_letters[$llai] . ($fai + 3))->applyFromArray($styleCoursesHeader);
                //courses stat border
                $objPHPExcel->getActiveSheet()->getStyle($first_letters[$lfai + 3] . ($fai + 3) . ':' . $first_letters[$llai] . ($lai + count($parent_group->child_groups) + 1))->applyFromArray($styleThickBrownBorderOutline);
                //courses bottom dashed
                $objPHPExcel->getActiveSheet()->getStyle($first_letters[$lfai + 4] . ($fai + 4) . ':' . $first_letters[$llai] . ($lai + count($parent_group->child_groups)))->applyFromArray($styleCourseResultsBorder);

                //staff border
                $objPHPExcel->getActiveSheet()->getStyle($first_letters[$lfai] . ($fai + 4) . ':' . $first_letters[$lfai] . ($lai + count($parent_group->child_groups)))->applyFromArray($styleThickBrownBorderOutline);

                //staff conditional
                $conditionalStyles = $objPHPExcel->getActiveSheet()->getStyle($first_letters[$lfai + 1] . ($fai + 4) . ':' . $first_letters[$lfai + 1] . ($lai + count($parent_group->child_groups)))->getConditionalStyles();
                array_push($conditionalStyles, $objConditional_minus_staff);
                $objPHPExcel->getActiveSheet()->getStyle($first_letters[$lfai + 1] . ($fai + 4) . ':' . $first_letters[$lfai + 1] . ($lai + count($parent_group->child_groups)))->setConditionalStyles($conditionalStyles);

                //staff center
                $objPHPExcel->getActiveSheet()->getStyle($first_letters[$lfai] . ($fai + 3) . ':' . $first_letters[$lfai + 1] . ($lai + count($parent_group->child_groups) + 1))->getAlignment()
                    ->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER)
                    ->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

                $fai = $lai + count($parent_group->child_groups) + 1;
                $lai = $fai + 3;
                $objPHPExcel->getActiveSheet()->getStyle($first_letters[$lfai] . $fai . ':' . $first_letters[$llai] . $lai)->getFont()->setBold(true);
            }
        }

        for ($i = 0; $i < $last_allowed_letter_index; $i++) {
            //echo $first_letters[$i];
            $objPHPExcel->getActiveSheet()->getColumnDimension($first_letters[$i])->setAutoSize(true);
        }

        $objPHPExcel->getActiveSheet()->fromArray($dataArray, NULL, 'B3');

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

    /**
     * Generate table for total results
     *
     * @param $results
     * @param $courses
     * @param string $caption
     *
     * @return array
     */
    static function generateTotalTable($results, $courses, $caption = '')
    {
        $data = [];
        $total_staff = $total_excluded_staff = 0;
        foreach ($courses as $course) {
            $total_overall[$course->id] = 0;
        }
        $data[] = ['', '', '', $caption, '', ''];
        $headers = ['Total Staff', ' ', ' ', ' '];
        foreach ($courses as $course) {
            $headers[] = $course->course_name;
        }
        $data[] = $headers;
        foreach ($results as $result) {
            $show_data = [];
            $total_staff += $result->total_users;
            $total_excluded_staff += $result->total_blocked_users;
            $diff_total_excl = $result->total_users - $result->total_blocked_users;
            $show_data[] = (string)$diff_total_excl;
            $show_data[] = '';
            $show_data[] = '';
            $show_data[] = $result->ug_name;
            foreach ($courses as $course) {
                $show_data[] = ($result->total[$course->id] ? ($result->total[$course->id] / $diff_total_excl) : '0');
                $total_overall[$course->id] += $result->total[$course->id];
            }
            $data[] = $show_data;
        }
        $overall_data = [];
        $overall_data[] = (string)($total_staff-$total_excluded_staff);
        $overall_data[] = '';
        $overall_data[] = '';
        $overall_data[] = 'Overall';

        $diff_total_excl = $total_staff - $total_excluded_staff;
        foreach ($courses as $course) {
            $overall_data[] = ($total_overall[$course->id] ? ($total_overall[$course->id] / $diff_total_excl) : '0');
        }
        $data[] = $overall_data;
        return $data;
    }
}