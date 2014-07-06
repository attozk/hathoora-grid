<?php
namespace hathoora\grid;

use hathoora\container,
    hathoora\database\dbAdapter;

class grid extends container
{
   /**
     * Render grid given an array of data\table
     *
     * @param array $arrGridData
     * @param bool $return or echo
    * @return string
    */
    public static function renderGrid(&$arrGridData, $return = false)
    {
        $table_id = isset($arrGridData['table']['id']) ? $arrGridData['table']['id'] : 'htgrid_'. time() . rand(1,99999);
        $table_class = isset($arrGridData['table']['class']) ? $arrGridData['table']['class'] : null;
        $grid = $noResultsTableClass = $hasNoResults = null;

        $hathooraGridJS = '/_assets/_hathoora/hathooraGrid.js';
        if (!empty($arrGridData['javascript']['gridPluginURL']))
            $hathooraGridJS = $arrGridData['javascript']['gridPluginURL'];

        $gridHeader = $gridPager = $gridTable = $gridPager = null;
        $htmlAppend = isset($arrGridData['html']['append']) ? $arrGridData['html']['append'] : null;
        $table_id = $arrGridData['table']['id'];
        $onGridReadyCallBack = !empty($arrGridData['javascript']['onGridReadyCallBack']) ? $arrGridData['javascript']['onGridReadyCallBack'] : 'null';
        $ajaxCalls = isset($arrGridData['javascript']['ajax']) ? $arrGridData['javascript']['ajax'] : true;

        // pagination?
        if (empty($arrGridData['table']['pagination']))
            $arrGridData['table']['pagination'] = array('\hathoora\grid\pagination', 'simple');
        else if (is_string($arrGridData['table']['pagination']))
            $arrGridData['table']['pagination'] = array('\hathoora\grid\pagination', $arrGridData['table']['pagination']);
        
        if (isset($arrGridData['table']['template']))
            require($arrGridData['table']['template']);
        // load default template
        else
            require(__DIR__ .'/template.php');

        if (!empty($arrGridData['debug']))
            $htmlAppend .= '<br/><br/>' . $arrGridData['queryTotal'] . '<br/><br/>' . $arrGridData['queryRow'] . '<br/><pre>'. print_r($arrGridData, true) .'</pre>';

        // when not ajax call
        if (empty($arrGridData['pajax']))
        {
            $grid = $gridHeader .'
            <div id="'. $table_id .'_inner" htg-table_id="'. $table_id .'" class="hathooraGrid '. $table_class .' ' .$noResultsTableClass .'">';

            if ($ajaxCalls)
            {
                $grid .= '
                 <script type="text/javascript">
                    var hathooraGrid = hathooraGrid || {"tables": {}};
                    $(document).ready(function()
                    {
                        // load hathooraGrid if not already loaded
                        if (jQuery().hathooraGrid == undefined)
                        {
                            $.ajax({
                                url: "'. $hathooraGridJS .'",
                                dataType: "script",
                                async: false,
                                cache: true,
                                success: function(){}
                            });
                        }
                        
                        hathooraGrid["tables"]["'.$table_id.'"] = {
                                                                    "id":  "'. $table_id .'",
                                                                    "sort":  "'. $arrGridData['sort'] .'",
                                                                    "order":  "'. $arrGridData['order'] .'",
                                                                    "page": "'. $arrGridData['page'] .'",
                                                                    "url": "' . $arrGridData['table']['sort']['url'] .'",
                                                                    "limit": "'. $arrGridData['limit'] .'",
                                                                    "dynamic": "'. (!empty($arrGridData['table']['fields']['dynamic']) ? 1 : 0 ).'",
                                                                    "columns": '. (!empty($arrGridData['table']['columnsJS']) ? json_encode($arrGridData['table']['columnsJS']) : '{}') .'
                                                                }

                        $("#'. $table_id .'").hathooraGrid({"onReady": '. $onGridReadyCallBack .', "isHackReady": true});
                    });
                </script>';
            }

            $grid .=
                (!empty($arrGridData['table']['options']['topPager']) ? '<div class="hathooraPreTable">'. $gridPager .'</div>' : null ) .
                $gridTable . 
                (!$hasNoResults && (isset($arrGridData['table']['options']['bottomPager']) && $arrGridData['table']['options']['bottomPager'] !== false) ? '<div class="hathooraPostTable"> '. $gridPager .'</div>' : null) .
                $htmlAppend .'
            </div>';
        }
        // is ajx call
        else
        {
            if ($ajaxCalls)
            {
                $grid = '
                <script type="text/javascript">
                    var hathooraGrid = hathooraGrid || {"tables": {}};
                    $(document).ready(function()
                    {
                        // load hathooraGrid if not already loaded
                        if (jQuery().hathooraGrid == undefined)
                        {
                            $.ajax({
                                url: "'. $hathooraGridJS .'",
                                dataType: "script",
                                cache: true,
                                async: false,
                                success: function(){}
                            });
                        }

                        hathooraGrid["tables"]["'.$table_id.'"] = {
                                                        "id":  "'. $table_id .'",
                                                        "sort":  "'. $arrGridData['sort'] .'",
                                                        "order":  "'. $arrGridData['order'] .'",
                                                        "page": "'. $arrGridData['page'] .'",
                                                        "url": "' . $arrGridData['table']['sort']['url'] .'",
                                                        "limit": "'. $arrGridData['limit'] .'",
                                                        "dynamic": "'. (!empty($arrGridData['table']['fields']['dynamic']) ? 1 : 0 ).'",
                                                        "columns": '. json_encode($arrGridData['table']['columnsJS']) .' }

                        $("#'. $table_id .'").hathooraGrid({"onReady": '. $onGridReadyCallBack .'});
                    });
                </script>';
            }

            $grid .=
            ( !empty($arrGridData['table']['options']['topPager']) ? '<div class="hathooraPreTable">'. $gridPager .'</div>' : null ) .
            $gridTable .
            (!$hasNoResults && (isset($arrGridData['table']['options']['bottomPager']) && $arrGridData['table']['options']['bottomPager'] !== false) ? '<div class="hathooraPostTable"> '. $gridPager .'</div>' : null) .
            $htmlAppend;
        }

        return $grid;
    }

    /**
     * Run a sql statement
     *
     * @param array $arrParams that have
     * $arrParams that have:
     *  dsn: which dsn to use
     *  sort: the field to sort on
     *  order: the order asc|desc
     *  start: the start of limit
     *
     * @todo complete this
     * @param bool $render
     * @return array|mixed
     */
    public static function sqlRun($arrParams, $render = false)
    {
        $dsn = !empty($arrParams['dsn']) ? $arrParams['dsn'] : self::getDSN();

        // debug
        $queryTotalDebug = !empty($arrParams['queryTotalDebug']) ? $arrParams['queryTotalDebug'] : false;
        $queryRowDebug = !empty($arrParams['queryRowDebug']) ? $arrParams['queryRowDebug'] : false;
        // stores results
        $arrGridData = array('total' => null, 'rows' => array());
        // query query
        $queryTotal = !empty($arrParams['queryTotal']) ? $arrParams['queryTotal'] : null;
        $skipTotal = !empty($arrParams['skipTotal']) ? $arrParams['skipTotal'] : false;
        $totalQueryHasResults = false;
        // rows query and related variables
        $queryRow =  !empty($arrParams['queryRow']) ? $arrParams['queryRow'] : null;
        $sort = !empty($arrParams['sort']) ? $arrParams['sort'] : null;
        $order = !empty($arrParams['order']) ? $arrParams['order'] : null;
        $start = !empty($arrParams['start']) ? $arrParams['start'] : 0;
        $page = !empty($arrParams['page']) ? (int) $arrParams['page'] : 1;
        $limit = !empty($arrParams['limit']) ? (int) $arrParams['limit'] : 100;
        $orderBy = $limitClause = null;
        // don't run any sql and go with the flow
        $noSQL = isset($arrParams['noSQL']) ? $arrParams['noSQL'] : false;
        
        // set them again so we don't get undefined variable notices
        $arrParams['sort'] = $sort;
        $arrParams['order'] = $order;
        $arrParams['start'] = $start;
        $arrParams['limit'] = $limit;

        // queryRowOuterSelect wraps row query which helps in sorting derived fields
        $queryRowOuterSelect = !empty($arrParams['queryRowOuterSelect']) ? $arrParams['queryRowOuterSelect'] : false;
        
        // an array containing function to call for every row in result to which we pass $row as referrence
        // rowFunctions must be an array of arrays that can be input to call_user_func_array()
        $rowFunctions = !empty($arrParams['rowFunctions']) ? $arrParams['rowFunctions'] : null;
        
        // field to use as index when creating results set
        $primaryKey = !empty($arrParams['primaryKey']) ? $arrParams['primaryKey'] : null;
        
        // similiar to rowFunctions an array of functions to call at the end of row loops
        // to which we pass the entire $arrGridData['rows'] as referrence
        $dataFunctions = !empty($arrParams['dataFunctions']) ? $arrParams['dataFunctions'] : null;
       
        // sort
        if ($sort && $order)
        {
            $orderBy = $dsn->escape($sort) . ' ' . $dsn->escape($order);
            $orderBy = 'ORDER BY '. $orderBy;
        }
        
        // limit
        if (!$start)
        {
            $start = ($page - 1) * $limit;
            if ($start < 1) $start = 0;
        }        
        if ($limit)
            $limitClause = 'LIMIT '. $dsn->escape($start) .', '. $dsn->escape($limit);

        // get total results
        if ($noSQL != true && $skipTotal == false && $queryTotal)
        {
            if ($queryTotalDebug)
                echo 'Total Query:<br/>' . nl2br($queryTotal);
            try
            {
                $stmt = $dsn->query($queryTotal);
                if ($stmt && $stmt->rowCount())
                {
                    $totalQueryHasResults = true;
                    $row = $stmt->fetchArray();
                    $arrGridData['total'] = array_pop($row);
                }
            }
            catch (\Exception $e)
            {
                // fail silently
            }
        }

        // get the rows
        if ($noSQL != true && $queryRow && 
           ($skipTotal || (!$skipTotal && $totalQueryHasResults)))
        {
            // outer select?
            if ($queryRowOuterSelect)
            {
                // we need to remove a.field from the order, it will messup
                $orderBy = preg_replace('/ORDER BY (.+?)\./i', 'ORDER BY ', $orderBy);
                
                $query = 'SELECT o.*  FROM ( '. $queryRow .') o '. $orderBy .' '. $limitClause;
            }
            else
                $query = $queryRow . ' '. $orderBy .' '. $limitClause;            
            
            if ($queryRowDebug)
                echo 'Row Query:<br/>' . nl2br($query);

            try
            {
                $stmt = $dsn->query($query);
                if ($stmt && $stmt->rowCount())
                {
                    while ($row = $stmt->fetchArray())
                    {
                        // any row functions
                        if (is_array($rowFunctions))
                        {
                            foreach ($rowFunctions as $rowFunction)
                            {
                                if (is_callable($rowFunction))
                                    call_user_func_array($rowFunction, array(&$row));
                            }
                        }

                        if ($primaryKey && isset($row[$primaryKey]))
                            $arrGridData['rows'][$row[$primaryKey]] = $row;
                        else
                            $arrGridData['rows'][] = $row;
                    }

                    // any data functions
                    if (is_array($dataFunctions))
                    {
                        foreach($dataFunctions as $dataFunction)
                        {
                            $arrDataFunctionExtraParams = null;
                            if (count($dataFunction) > 2)
                                $arrDataFunctionExtraParams = array_pop($dataFunction);
                            if (is_callable($dataFunction))
                            {
                                $args = array();
                                $args[0] =& $arrGridData['rows'];
                                if (is_array($arrDataFunctionExtraParams))
                                    $args = array_merge($args, $arrDataFunctionExtraParams);

                                call_user_func_array($dataFunction, $args);
                            }
                        }
                    }
                }
            }
            catch (\Exception $e)
            {
                // fail silently
            }
        }

        if (!$render)
        {
            // skip totals?
            if ($skipTotal && count($arrGridData))
            {
                unset($arrGridData['total']);
                $arrGridData = $arrGridData['rows'];
            }
            return $arrGridData;
        }
        else
        {
            // merge arrGridData into arrParams
            $arrParams['data']['total'] =& $arrGridData['total'];
            $arrParams['data']['rows'] =& $arrGridData['rows'];
            return self::renderGrid($arrParams, true);
        }
    }
    
    /**
     * Builds sql select
     */
    public static function sqlBuildSelect($arrSelect, $addSelect = false)
    {
        $select = null;
        
        if (is_array($arrSelect))
        {
            foreach ($arrSelect as $f => $v)
            {
                if ($select)  $select .= ', ';
                if (preg_match('/^(.+?):(literal)$/i', $f, $arrMatch))
                    $select .= $v;
                else
                    $select .= self::getDSN()->escape($v);
            }
        }
        
        if ($addSelect && $select)
            $addSelect = ' SELECT ' . $addSelect;
        
        return $select;
    }

    /**
     * Builds sql where criteria
     *
     * @param array $arrWhere
     * @param bool $whereClause when true we will add where clause
     */
    public static function sqlBuildWhere($arrWhere, $whereClause = true)
    {
        $where = null;
        if (is_array($arrWhere))
        {
            if ($whereClause)
                $where = ' WHERE 1 ';
            foreach ($arrWhere as $f => $v)
            {
                if ($where)
                    $where .= ' AND ';

                $field = $f;

                // special suff?
                if (preg_match('/^(.+?):(literal|int|string)$/i', $f, $arrMatch))
                {
                    $type = array_pop($arrMatch);
                    $f =  self::getDSN()->escape(array_pop($arrMatch));

                    // literal - becareful with these!!!!
                    if ($type == 'literal')
                    {
                        // make sure this literal was not passed in the URL (GET!!)
                        if (!empty($_GET['where'][$field]))
                            unset($_GET['where'][$field]);
                        else if (!empty($_POST['where'][$field]))
                            unset($_POST['where'][$field]);
                        else
                        {
                            $where .= $v;
                        }
                    }
                    else if ($type == 'int')
                        $where .= $f .' IN ('. self::getDSN()->escape($v) .') ';
                    else if ($type == 'string')
                        $where .= $f . self::sqlWhereSmartLike($v);

                    else
                        $where .= $f .' = "'. self::getDSN()->escape($v) .'" ';
                }
                else
                    $where .= $f .' = "' . self::getDSN()->escape($v) . '" ';
            }
        }

        return $where;
    }

    /**
     * Replaces \* with %
     * Would return ' LIKE "%when $v has wildcard" '
     * Else would return ' = "$v"
     */
    public static function sqlWhereSmartLike($v)
    {   
        $match = null;
        
        $v = trim($v);
        if (preg_match('/\*/', $v))
            $match = ' LIKE "'. self::getDSN()->escape(str_replace('*', '%', $v)) . '" ';
        else 
            $match = ' = "'. self::getDSN()->escape($v) . '" ';
            
        return $match;
    }
    
    /**
     * Builds sql join criters
     *
     * @param array $arrJoin 
     *  ex: array('users' => 'INNER JOIN users u ON (u.user_id = f.user_id))
     */
    public static function sqlBuildJoin($arrJoin)
    {
        $joins = null;
        if (is_array($arrJoin))
        {
            foreach ($arrJoin as $v)
            {
                $joins .= $v . " \n";
            }
            $joins = " \n" . $joins;
        }
        
        return  $joins;
    }
    
    /**
     * Builds group by statement..
     *
     * @param array $arrGroup 
     *  ex: 
     *      array('user_id' => 'user_id'),
     *      array('user_id' => 'user_id', 'date' => 'date'),
     * @param bool $addGroup syntax
     */
    public static function sqlBuildGroupBy($arrGroup, $addGroup = false)
    {
        $groupBy = $group = null;
        
        if (is_array($arrGroup))
        {
            $group = '';
            foreach ($arrGroup as $k)
            {
                if ($group)  $group .= ', ';
                $group .= self::getDSN()->escape($k);
            }
        }
        
        if ($addGroup && $group)
            $groupBy = ' GROUP BY ' . $group;
        
        return $groupBy;    
    }    
    
    /**
     * This function prepare the grid array by setting limit, sorting, selected fields, row criteria etc..
     * This function also makes sure that a user cannot user a property (sort, search, select etc..) when it is
     * not specified in the $arrGridPrepare
     *
     * Once the data has been processed it is then ready to be handed off to sqlRun() function
     *
     * @param array $arrGridPrepare 
     * @param array $arrFormData (which is usually via GET or POST)
     */
    public static function prepare(&$arrGridPrepare, $arrFormData)
    {   
        #0 add meta info 
        $arrGridPrepare['meta'] = array();
        
        #1 assign a unqiue id to every table, this unique id is used when making ajax calls
        if (!empty($arrGridPrepare['table']['id']))
            $table_id = $arrGridPrepare['table']['id'];
        else if (!empty($arrFormData['hathooraGrid_id']))
            $table_id = $arrGridPrepare['table']['id'];
        else
        {
            $table_id = 'hathooraGrid_' . rand(9,99999999);  
            $arrGridPrepare['table']['id'] = $table_id;
        }

        #2a figure out the limit (for SQL)
        if (!empty($arrGridPrepare['table']['limit']['fixed']))
            $arrGridPrepare['limit'] = $arrGridPrepare['table']['limit']['fixed'];
        else
        {
            // limit
            if (!empty($arrFormData['limit']))
                $arrGridPrepare['limit'] = (int) $arrFormData['limit'];
           
            // default limit
            else if (!empty($arrGridPrepare['table']['limit']['default']) && empty($arrFormData['limit']))
                $arrGridPrepare['limit'] = $arrGridPrepare['table']['limit']['default'];
        }

        // max limit
        if (!empty($arrGridPrepare['table']['limit']['max']) && !empty($arrGridPrepare['limit']) && $arrGridPrepare['limit'] > $arrGridPrepare['table']['limit']['max'])
            $arrGridPrepare['limit'] = $arrGridPrepare['table']['limit']['max'];

        // cap limit when not able to assign a limit
        if (empty($arrGridPrepare['limit']))
            $arrGridPrepare['limit'] = 100;

        #2b figure out page (for SQL & pagination)
        if (empty($arrGridPrepare['page']))
        {
            $page = !empty($arrFormData['page']) ? (int) $arrFormData['page'] : 1;
            $arrGridPrepare['page'] = $page;
        }
        
        #2c figure out page (for SQL & pagination)
        if (empty($arrGridPrepare['start']))
        {
            $start = ($arrGridPrepare['page'] - 1) * $arrGridPrepare['limit'];
            if ($start < 1) $start = 0;
            $arrGridPrepare['start'] = $start;
        }

        
        #3 figure out the columns we need to display in table
        $tableFieldsAvailable = isset($arrGridPrepare['table']['fields']['available']) ? $arrGridPrepare['table']['fields']['available'] : null;
        if (is_array($tableFieldsAvailable))
        {
            // @todo check file before loading..
            #sqlLoadClassTest(true);
            $callable = is_callable($tableFieldsAvailable);
            #sqlLoadClassTest(false);
            
            if ($callable)
            {
                #3a figure out which columns are actually available for selection
                $arrFieldsAvailable = call_user_func_array($tableFieldsAvailable, array());
                
                // any jail?
                $arrFieldsPossibleJail = isset($arrGridPrepare['table']['fields']['jail']) ? $arrGridPrepare['table']['fields']['jail'] : null;
                $arrFieldsJail = array(); // fill it with confirm jailed fields
                if (is_array($arrFieldsAvailable) && is_array($arrFieldsPossibleJail))
                {
                    foreach ($arrFieldsPossibleJail as $field)
                    {
                        if (isset($arrFieldsAvailable[$field]))
                            $arrFieldsJail[$field] =& $arrFieldsAvailable[$field];
                    }
                }
                
                // these are the fields that are available for selection
                if  (count($arrFieldsJail))
                    $arrFields =& $arrFieldsJail;
                else
                    $arrFields =& $arrFieldsAvailable;
                    
                #3b prepare the column array which is used by renderTable to draw table
                if (is_array($arrFields))
                {
                    $arrColumnsPossibleType = 'indexed';
                    // dynamic columns that are passed by the ajax call?
                    if (!empty($arrGridPrepare['table']['fields']['dynamic']) && !empty($arrFormData['columns']) && is_array($arrFormData['columns']))
                    {
                        $arrColumnsPossible = $arrFormData['columns'];
                        $arrColumnsPossibleType = 'associative';
                    }
                    // default columns specified
                    else if (!empty($arrGridPrepare['table']['fields']['default']) && count($arrGridPrepare['table']['fields']['default']))
                        $arrColumnsPossible = $arrGridPrepare['table']['fields']['default'];

                    // if still not able to figure out possible columns then use $arrFields
                    if (empty($arrColumnsPossible) || !count($arrColumnsPossible))
                    {
                        $arrColumnsPossible = $arrFields;
                        $arrColumnsPossibleType = 'associative';
                    }

                    #3c now figure out among the possible columns which ones are actually defined and can be used
                    if (is_array($arrColumnsPossible))
                    {
                        foreach($arrColumnsPossible as $field => $vfield)
                        {
                            // be careful because can have associative or indexed array based on how it was prepared
                            if ($arrColumnsPossibleType == 'indexed')
                                $field = $vfield;

                            if (isset($arrFields[$field]) && is_array($arrFields[$field]))
                            {
                                $arrField =& $arrFields[$field];

                                // name of the field?
                                if (!empty($arrField['name']))
                                {
                                    $cleanName = self::cleanString($arrField['name']);
                                    if ($cleanName)
                                        $arrField['name'] = $cleanName;
                                }

                                if (empty($arrField['name']))
                                    $arrField['name'] = $field;

                                if (!array_key_exists('canDel', $arrField))
                                    $arrField['canDel'] = true;

                                // set the default value of dbField
                                if (!array_key_exists('dbField', $arrField))
                                    $arrField['dbField'] = $field;

                                // any dependencies?
                                if (isset($arrField['dependency']) && is_array($arrField['dependency']))
                                    self::prepareDependencyHandler('column', $field, $arrField, $arrGridPrepare, $arrFormData);
                                if (!empty($arrField['dbField']))
                                    $arrGridPrepare['selectField'][$arrField['dbField']] = $arrField['dbField'];


                                $arrGridPrepare['table']['columns'][$field] =& $arrField;
                                $arrGridPrepare['table']['columnsJS'][$field] = array(
                                                                                    'field' => $field,
                                                                                    'name' => isset($arrField['name']) ? $arrField['name'] : $field,
                                                                                    'sort' => isset($arrField['sort']) ? $arrField['sort'] : 0,
                                                                                );
                            }
                        }
                    }
                }
            }
        }

        #4 sort & order
        if (!empty($arrFormData['sort']))
        {
            // is the field even marked sortable?
            if (!empty($arrFields[$arrFormData['sort']]['sort']))
            {
                // field has dbField?
                if (!empty($arrFields[$arrFormData['sort']]['dbField']))
                    $arrGridPrepare['sort'] = self::removeNand($arrFields[$arrFormData['sort']]['dbField']);
                else
                    $arrGridPrepare['sort'] = self::removeNand($arrFormData['sort']);

                if (!empty($arrFormData['order']))
                    $arrGridPrepare['order'] = $arrFormData['order'];
            }
        }

        // default sort & order
        if (empty($arrGridPrepare['sort']) && !empty($arrGridPrepare['table']['sort']['default']))
            $arrGridPrepare['sort'] = $arrGridPrepare['table']['sort']['default'];

        if (empty($arrGridPrepare['order']) && !empty($arrGridPrepare['table']['order']['default']))
            $arrGridPrepare['order'] = $arrGridPrepare['table']['order']['default'];

        if (empty($arrGridPrepare['sort']) && !empty($arrGridPrepare['order']))
            $arrGridPrepare['order'] = 'desc';

        #5 output
        if (empty($arrGridPrepare['table']['output']) && !empty($arrFormData['output']))
            $arrGridPrepare['table']['output'] = $arrFormData['output'];
        if (empty($arrGridPrepare['table']['output']))
            $arrGridPrepare['table']['output'] = 'html';

        #6a search fields
        // fields a user can search on
        $arrSearchableFields = array();

        // get the columns that are searchable, first from the ['table']['column']['fields']
        if (!empty($arrFields) && is_array($arrFields))
        {
            foreach ($arrFields as $_field => $_arrField)
            {
                if (!empty($_arrField['search']) && is_array($_arrField['search']))
                {
                    if (!empty($_arrField['search']['type']))
                    {
                        if (!empty($_arrField['search']['operations']))
                        {
                            $_operations = explode(',', $_arrField['search']['operations']);
                            $_searchArr = array();
                            foreach($_operations as $_operation)
                            {
                                $_operation = trim($_operation);
                                $_searchArr[$_operation] = $_operation;
                            }

                            $arrSearchableFields[$_field] = $_searchArr;
                            $arrGridPrepare['table']['columnsJS'][$field]['search'] = $_searchArr;
                        }
                    }
                }
            }
        }

        #6 where criteria
        $arrWherePossible = null;
        if (!empty($arrFormData['where']) && is_array($arrFormData['where']))
            $arrWherePossible =& $arrFormData['where'];
        $arrWhere = array(); // fields that are searched

        if (is_array($arrWherePossible) && count($arrSearchableFields))
        {
            // loop over where criteria and make sure the fields are searchable so we can prepare where criteria
            foreach($arrWherePossible as $where => $value)
            {   
                if (preg_match('/[a-zA-z0-9_\.]+(|:)/i', $where, $arrMatch))
                {
                    $field = $arrMatch[0];
                    
                    // is field even searchable?
                    if (!empty($arrSearchableFields[$field]) && is_array($arrSearchableFields[$field]))
                    {
                        $arrField = $arrFields[$field];
                        if (!empty($arrField['dependency']) && is_array($arrField['dependency']))
                            self::prepareDependencyHandler('where', $field, $arrField, $arrGridPrepare, $arrFormData);
                            
                        $arrWhere[$where] = $value;
                    }
                }
            }
        }

        // make rowWhere the same as totalWhere
        if (count($arrWhere))
            $arrGridPrepare['whereTotal'] = $arrGridPrepare['whereRow'] = $arrWhere;
        
        // ajax URL
        if (empty($arrGridPrepare['table']['sort']['url']))
            $arrGridPrepare['table']['sort']['url'] = $_SERVER['REQUEST_URI'];

        // cleanup duplicate for duplicate params
        // @http://stackoverflow.com/questions/2613063/remove-duplicate-from-string-in-php
        if ($arrGridPrepare['table']['sort']['url'])
        {
            $url = parse_url($arrGridPrepare['table']['sort']['url'], PHP_URL_PATH);
            $params = parse_url($arrGridPrepare['table']['sort']['url'], PHP_URL_QUERY);
            if ($params)
            {
                parse_str($params, $paramsArr);
                $params = http_build_query($paramsArr);
            }

            $arrGridPrepare['table']['sort']['url'] = $url . ($params ? '?'. $params : null);
        }

        // ajax requesy?
        if (!array_key_exists('pajax', $arrGridPrepare) && container::getRequest()->isAjax())
            $arrGridPrepare['pajax'] = true;
    }
    
    /**
     * Handles dependecies when certain fields are selected or searched
     *
     * @param string $handleType column, where
     * @param string $field current field
     * @param array $arrField details about field
     * @param array $arrGridPrepare
     * @param array $arrFormData the form|GET|POST
     */
    public static function prepareDependencyHandler($handleType, $field, &$arrField, &$arrGridPrepare, &$arrFormData)  
    {    
        // select fields?
        if (isset($arrField['dependency']['selectField']) && is_array($arrField['dependency']['selectField']))
        {
            foreach($arrField['dependency']['selectField'] as $f => $v)
            {
                $arrGridPrepare['selectField'][$f] = $v;
            }
        }
        
        // total joins
        if (isset($arrField['dependency']['joinTotal']) && is_array($arrField['dependency']['joinTotal']))
        {
            foreach($arrField['dependency']['joinTotal'] as $f => $v)
            {
                $arrGridPrepare['joinTotal'][$f] = $v;
            }
        }

        // row joins
        if (isset($arrField['dependency']['joinRow']) && is_array($arrField['dependency']['joinRow']))
        {
            foreach($arrField['dependency']['joinRow'] as $f => $v)
            {
                $arrGridPrepare['joinRow'][$f] = $v;
            }
        }

        // rowFunctions
        if (isset($arrField['dependency']['rowFunctions']) && is_array($arrField['dependency']['rowFunctions']))
        {
            foreach ($arrField['dependency']['rowFunctions'] as $f => $v)
            {
                $arrGridPrepare['rowFunctions'][$f] = $v;
            }
        }

        // total groups
        if (isset($arrField['dependency']['groupTotal']) && is_array($arrField['dependency']['groupTotal']))
        {
            foreach ($arrField['dependency']['groupTotal'] as $f => $v)
            {
                $arrGridPrepare['groupTotal'][$f] = $v;
            }
        }

        // row groups
        if (isset($arrField['dependency']['groupRow']) && is_array($arrField['dependency']['groupRow']))
        {
            foreach ($arrField['dependency']['groupRow'] as $f => $v)
            {
                $arrGridPrepare['groupRow'][$f] = $v;
            }
        }

        // dataFunctions
        if (isset($arrField['dependency']['dataFunctions']) && is_array($arrField['dependency']['dataFunctions']))
        {
            foreach ($arrField['dependency']['dataFunctions'] as $f => $v)
            {
                $arrGridPrepare['dataFunctions'][$f] = $v;
            }
        }
    }

    /**
     * Helper function that parse $data['rows'] and returns pk ids or other mapping ids
     *
     * @param array $arrRows
     * @param string $mapField $arrRows[$mapField] should have thie
     * @param string $dontMapWhenFieldExists we will skip all rows that have $arrRows[$dontMapWhenFieldExists]
     * @param object $db to escape against
     * 
     * @return array containing
     *      arrMapping: mapping of keys from $arrRows and $mapField from $arrRows
     *      arrIds: if mapField == null then its keys of $arrRows, else it is the mapping b/w $mapFieldID and key
     *      strIds: is the db escaped, comma seperated, double quoted string ready to be used for sql 
     */
    public static function getDataFunctionIds(&$arrRows, $mapField = null, $dontMapWhenFieldExists = null, $db = null)
    {
        $arrMapping = array();
        $arrIds = array();
        $strIds = null;
        
        if (is_array($arrRows) && ($db = self::getDSN()))
        {
            foreach($arrRows as $id => $arrRow)
            {
                if ($mapField)
                {
                    if (isset($arrRow[$mapField]))
                    {
                        if (isset($dontMapWhenFieldExists) && isset($arrRow[$dontMapWhenFieldExists]))
                            continue;

                        $map_id = $arrRow[$mapField];
                        $arrMapping[$map_id][$id] = $id;
                    }
                }
                else
                    $map_id = $id;
                
                if (isset($map_id) && !isset($arrIds[$map_id]))
                {
                    if ($strIds) $strIds .= ', ';
                    $strIds .= "'". $db->escape($map_id) ."'";
                    $arrIds[$map_id] = $map_id;
                }
            }
        }
        
        return array(
                        'arrMapping' => $arrMapping,
                        'arrIds' => $arrIds,
                        'strIds' => $strIds
                    );
    }
    
    /**
     * Helper function that merge mapping ids to arrRows
     *
     * @param array $arrRows
     * @param array $arrResults sql results from $arrIds/$strIds usually. The key of this array is the $mapField from getDataFunctionIds()
     * @param array $arrMapping from getDataFunctionIds()
     */
    public static function getDataFunctionMergeMapping(&$arrRows, &$arrResults, &$arrMapping, $arrRowsAdditonalKey = null)
    {
        if (is_array($arrResults))
        {
            foreach($arrResults as $mapId => $arrResult)
            {
                if (isset($arrMapping[$mapId]) && is_array($arrMapping[$mapId]))
                {
                    foreach($arrMapping[$mapId] as $rowId)
                    {
                        if (isset($arrRows[$rowId]))
                        {
                            if (isset($arrRowsAdditonalKey))
                                $arrRows[$rowId][$arrRowsAdditonalKey] = $arrResult;
                            else
                                $arrRows[$rowId] = array_merge($arrResult, $arrRows[$rowId]);
                        }
                    }
                }
            }
        }    
    }
    
    /**
     * used to remove HTML|nonsense chars from THEAD TH of render table for csv output
     */
    public static function cleanString($string)
    {
        $string = str_replace(array('&nbsp;', '<br/>', '<br>'), ' ', $string);
        $string = strip_tags($string);
        $string = str_replace(' ', ' ', $string);
        
        return $string;
    }
    
    /**
     * Toggles between two values
     *
     * @param mixed $v current value
     * @param mixed $a option a
     * @param mixed $b option b
     */
    public static function toggleValue($v, $a, $b)
    {
        if ($v == $a) return $b;
        return $a;
    }
    
    /**
     * Returns the value of token
     *
     * @param string $token that we want to get the value for ex: {{name}}
     * @param array $arrTokens that has a key of token ex: 'name' = 'xyz'
     * @return string
     */
    public static function deTokenize($token, &$arrTokens)
    {
        return \hathoora\helper\stringHelper::deTokenize($token, $arrTokens);
    }
    
    /**
     * Remove non alpha, non numeric and non dots from a string
     */
    public static function removeNand($input)
    {
        return preg_replace('/[^a-zA-Z0-9-_,\.\s]/', '', $input);
    }

    /**
     * Helper function for getting db connection
     * 
     * @param string $dsn_name defined in the config
     * @param bool $reBuild when true 
     * @return hathoora\database\db class
     */
    public static function getDSN($dsn_name = 'default', $reBuild = false)
    {
        return dbAdapter::getConnection($dsn_name, $reBuild);
    }
}