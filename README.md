
Pagination Yii2:

For pagination you have to use *unique* and *sortable* column in table.


        return new CustomDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $formIn->limit,
                'pagination_key'=>'_id',
                'prev'=>$formIn->prev = $formIn->prev == '' ? '' : new \MongoId($formIn->prev),
                'next'=>$formIn->next =$formIn->next == '' ? '' : new \MongoId($formIn->next),
                'class' => 'frontend\modules\v2\component\ApifonicaPagination'
            ]
        ]);
