<?php
    $gridHeader = $gridPager = null;
    $table_id = $arrGridData['table']['id'];

    // header: title
    if (!empty($arrGridData['table']['title']))
        $gridHeader .= '<div class="hathooraTitle">'. $arrGridData['table']['title'] .'</div>';

    // table: no results?
    $hasNoResults = false;
    if (empty($arrGridData['data']['rows']) || !count($arrGridData['data']['rows']))
    {
        $hasNoResults = true;
        if (!empty($arrGridData['table']['message']['noResults']))
            $gridTable = '<div class="noResults">'. $arrGridData['table']['message']['noResults'] .'</div>';
        else
            $gridTable = '<div class="noResults">No results found</div>';
    }
    else
    {
        $gridTable = null;

        if (!empty($arrGridData['table']['columns']) && is_array($arrGridData['table']['columns']))
        {
            // table head
            $gridTable = '
            <table id="'. $arrGridData['table']['id'] .'" class="hathooraTable '. (!empty($arrGridData['table']['class']) ?  $arrGridData['table']['class'] : null).'" cellspacing="0" cellpadding="0" border="0">';

            if (empty($arrGridData['table']['options']['noTableHead']))
            {
                $gridTable .= '
                    <thead>
                        <tr class="row">';

                            foreach ($arrGridData['table']['columns'] as $column => $arrColumn)
                            {
                                if (isset($arrColumn['isColumn']) && $arrColumn['isColumn'] === false)
                                    continue;

                                $columSpanDel = null;
                                if (!empty($arrGridData['table']['fields']['dynamic']) && !empty($arrColumn['canDel']))
                                    $columSpanDel = '<span class="hathooraColumnDel">x</span>';

                                $columSpanSort = $_columnIsOrdered  = null;
                                if (!empty($arrColumn['sort']))
                                {
                                    $_columnOrder = 'desc';

                                    // field is already sorted?
                                    if (!empty($arrGridData['sort']) && $arrGridData['sort'] == $arrColumn['dbField'])
                                    {
                                        $_columnIsOrdered = true;
                                        $_columnOrder = !empty($arrGridData['order']) ? $arrGridData['order'] : 'desc';
                                    }
                                    // default table sort
                                    else if (!empty($arrGridData['table']['order']) &&!empty($arrGridData['table']['order']['default']))
                                        $_columnOrder = $arrGridData['table']['order']['default'];

                                    $_columnOrder = strtolower($_columnOrder);
                                    // reverse the order
                                    if ($_columnOrder == 'asc')
                                    {
                                        $_columnOrder = 'desc';
                                        $_columnOrderIcon = '&#8595;'; // down arrow
                                    }
                                    else
                                    {
                                        $_columnOrder = 'asc';
                                        $_columnOrderIcon = '&#8593;'; // up arrow
                                    }
                                    $columSpanSort = '<span class="hathooraColumnSort" htg-order="'. $_columnOrder .'">'. $_columnOrderIcon .'</span>';
                                }

                                // column name
                                $columnName = $arrColumn['name'];

                                $columnClass = (!empty($arrColumn['classTH']) ? $arrColumn['classTH'] : null);
                                if ($_columnIsOrdered)
                                    $columnClass.= ' hathooraColumnSorted ';

                                $gridTable .= '
                                    <th
                                        htg-field="'. $column .'"
                                        class="'. $columnClass .'"
                                        '. (!empty($arrColumn['attrsTH']) ? $arrColumn['attrsTH']  : null) .'
                                        >
                                            <div class="hathooraColumnOptions">'.
                                                $columSpanSort .
                                                $columSpanDel .
                                            '
                                            </div>
                                            <div class="hathooraColumnName">'. $columnName .'</div>
                                    </th>';

                                // contains the same columns that are displayed in thead
                                $arrColumns[$column] = $column;
                            }
                $gridTable .= '
                        </tr>
                    </thead>
                    <tbody>';
            }
            else
            {
                foreach ($arrGridData['table']['columns'] as $column => $arrColumn)
                {
                    if (isset($arrColumn['isColumn']) && $arrColumn['isColumn'] === false)
                        continue;

                    // contains the same columns that are displayed in thead
                    $arrColumns[$column] = $column;
                }
            }

            // table rows
            $tr_class = null;

            foreach ($arrGridData['data']['rows'] as $id => $arrRow)
            {
                $tr_id  = null;
                if (!empty($arrGridData['table']['idRow']))
                    $tr_id = \hathoora\grid\grid::deTokenize($arrGridData['table']['idRow'], $arrRow);

                $tr_class = \hathoora\grid\grid::toggleValue($tr_class, 'odd', 'even');
                if (!empty($arrRow['classTR']))
                    $tr_class .= ' ' . \hathoora\grid\grid::deTokenize($arrRow['classTR'], $arrRow);

                $gridTable .= '
                <tr id="'. $tr_id . '" class="'. $tr_class .'">';

                // now loop over all the column arrays, remember contains the same columns that are displayed in thead
                foreach ($arrColumns as $column)
                {
                    $arrColumn = $arrGridData['table']['columns'][$column];
                    if (isset($arrRow[$column]))
                        $rowValue = $arrRow[$column];
                    else
                        $rowValue = @$arrRow[preg_replace('/^(.+?)\./','', $column)];

                    // special row stuff
                    if (!empty($arrColumn['content']))
                    {
                        // just add a link
                        if (!empty($arrColumn['content']['link']))
                            $rowValue = '<a href="'. \hathoora\grid\grid::deTokenize($arrColumn['content']['link'], $arrRow) .'">'. $rowValue .'</a>';
                        // call a function
                        else if (!empty($arrColumn['content']['function']) && is_array($arrColumn['content']['function']))
                        {
                            // when we want to pass the context
                            if (count($arrColumn['content']['function']) == 3)
                                $rowValue = \hathoora\grid\grid::deTokenize(call_user_func_array(array($arrColumn['content']['function'][0], $arrColumn['content']['function'][1]) , array($rowValue, &$arrRow, &$arrGridData, &$arrColumn['content']['function'][2])), $arrRow);
                            else
                                $rowValue = \hathoora\grid\grid::deTokenize(call_user_func_array($arrColumn['content']['function'], array($rowValue, &$arrRow, &$arrGridData)), $arrRow);
                        }
                        else if ($arrColumn['content'])
                            $rowValue = \hathoora\grid\grid::deTokenize($arrColumn['content'], $arrRow);
                    }

                    $gridTable .= '
                        <td
                            '. (!empty($arrColumn['classTD']) ? ' class="'. $arrColumn['classTD'] .'" ' : null) .'
                            '. (!empty($arrColumn['attrsTD']) ? $arrColumn['attrsTD']  : null) .'
                        >'
                            . $rowValue . '
                        </td>';
                }

                $gridTable .= '</tr>';
            }

            $gridTable .= '
                </tbody>
            </table>';
        }


        // showing X to Y of Z text
        $total = 0;
        if ($arrGridData['data']['total'] && is_numeric($arrGridData['start']))
        {
            $end = $arrGridData['start'] + $arrGridData['limit'];
            $total = $arrGridData['data']['total'];
            if ($end > $arrGridData['data']['total'])
                $end = $arrGridData['data']['total'];
            $startFrom = $arrGridData['start'] + 1;
            $dynamicInfo = 'Showing '. $startFrom .' to ' . $end .' of '. $arrGridData['data']['total'] . ' entries';
        }


        $gridPager = '
            '. ( !empty($dynamicInfo) ? '<div class="hathooraPaginatorInfo">'. $dynamicInfo .'</div>' : null );

        // pagination?
        $totalPages = @ceil($arrGridData['data']['total'] / $arrGridData['limit']);
        $page = $arrGridData['page'];
        if ($totalPages  > 1)
        {
            $gridPager .= '
            <div class="hathooraPaginator">';

            $sortURL = !empty($arrGridData['table']['sort']['url']) ? $arrGridData['table']['sort']['url'] : null;
            $arrPaginationParams = array(
                                            'totalPages' => $totalPages,
                                            'currentPage' => $page,
                                            'baseURL' => $sortURL);
            $arrPagination = call_user_func_array($arrGridData['table']['pagination'], array($arrPaginationParams));
            // list display
            foreach ($arrPagination as $arrPagi)
            {
                // If page has a link
                if (isset($arrPagi['url']))
                    $gridPager .= '<a href="#" class="hathooraPagi" htg-page="'. $arrPagi['text'] .'">'. $arrPagi['text'] .'</a>';
                // no link - just display the text
                 else
                    $gridPager .= '<a class="hathooraPagiActive">'. $arrPagi['text'] .'</a>';
            }
            $gridPager .= '
                </div>';
        }
    }