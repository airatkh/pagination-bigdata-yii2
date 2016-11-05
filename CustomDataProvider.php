<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace frontend\modules\v2\component;

use Yii;
use yii\data\ActiveDataProvider;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\db\QueryInterface;
use yii\helpers\ArrayHelper;
use yii\helpers\VarDumper;
use frontend\modules\v2\exception\ApicoServerErrorHttpException;

/**
 * ApifonicaDataProvider implements a data provider based on [[\yii\db\Query]] and [[\yii\db\ActiveQuery]].
 *
 * ApifonicaDataProvider provides data by performing DB queries using [[query]].
 *
 * The following is an example of using ApifonicaDataProvider to provide ActiveRecord instances:
 *
 * ~~~
 *
 *  $provider = new ApifonicaDataProvider([
 *      'query' => $query,
 *      'pagination' => [
 *          'pageSize' => $formIn->limit,
 *          'pagination_key' => 'id',
 *          'prev' => $formIn->prev,
 *          'next' => $formIn->next,
 *          'class' => 'frontend\modules\v2\component\ApifonicaPagination'
 *      ]
 *  ]);
 *
 * // get the posts in the current page
 * $posts = $provider->getModels();
 * ~~~
 *
 * And the following example shows how to use ApifonicaDataProvider without ActiveRecord:
 *
 * ~~~
 * $query = new Query;
 *
 * $provider = new ApifonicaDataProvider([
 *         'query' => $query,
 *         'pagination' => [
 *             'pageSize' => $formIn->limit,
 *             'pagination_key' => 'id',
 *             'prev' => $formIn->prev,
 *             'next' => $formIn->next,
 *             'class' => 'frontend\modules\v2\component\ApifonicaPagination'
 *         ]
 *     ]);
 *
 * // get the posts in the current page
 * $posts = $provider->getModels();
 * ~~~
 * @author Khisamov Airat <kh.airat14@gmail.com>
 * @since 2.0
 */
class ApifonicaDataProvider extends ActiveDataProvider
{
    /**
     * @inheritdoc
     * 1. Prepare Query for pagination
     * 2. Query data(fetchData)
     * 3. Clear data if necessary.
     *
     * ***Deprecated*** Get counts of models ***Deprecated***
     */
    protected function prepareModels()
    {
        if (!$this->query instanceof QueryInterface) {
            throw new InvalidConfigException('The "query" property must be an instance of a class that implements the QueryInterface e.g. yii\db\Query or its subclasses.');
        }

        $query = $this->processBeforeFetchedModels();
        $models =  $query->all($this->db);
        $models = $this->processAfterFetchedModels($models);

        /** @var \frontend\modules\v2\component\ApifonicaPagination $pagination */
        $pagination = $this->getPagination();
        $pagination->params = $this->prepareRequestParams($models);

        return $models;
    }

    /**
     * Prepare Query for pagination
     *
     * Depend on "Pagination key" and "Pagination Direction" prepare query request.
     */
    public function processBeforeFetchedModels()
    {
        /** @var \frontend\modules\v2\component\ApifonicaPagination $pagination */
        $pagination = $this->getPagination();
        $query = clone $this->query;

        $limit = $pagination->getLimit() + 1; //Check:Can we get MORE models. Should we create link "NEXT" or "PREV"
        $query->limit($limit);

        $key= $pagination->pagination_key;
        switch ($pagination->pag_direction) {
            case ApifonicaPagination::LINK_FIRST:
                $query->addOrderBy([$key => SORT_DESC]);
                break;
            case ApifonicaPagination::LINK_PREV:
                $query->andWhere(['>', $key, $pagination->prev])->addOrderBy([$key => SORT_ASC]);
                break;
            case ApifonicaPagination::LINK_NEXT:
                $query->andWhere(['<', $key, $pagination->next])->addOrderBy([$key => SORT_DESC]);
                break;
            default:
                $msg = "Failed prepare query for pagination.";
                \Yii::error($msg, __METHOD__);
                throw new ApicoServerErrorHttpException();
        }
        return $query;
    }

    /**
     * Check exist more Models than param "limit". Init param $pagination->more_data_exist.
     * Extract First or Last array of model depend on $pagination->more_data_exist and $pagination->pag_direction.
     *
     * Attention:
     * For $pagination->pag_direction we have to do array_reverse($models)
     *
     * @param $models array Result of fetch Models for pagination.
     * @return array of Models.
     * @throws ApicoServerErrorHttpException
     */
    public function processAfterFetchedModels($models)
    {
        /** @var \frontend\modules\v2\component\ApifonicaPagination $pagination */
        $pagination = $this->getPagination();
        $this->checkExistMoreModels($models);

        switch ($pagination->pag_direction) {
            case ApifonicaPagination::LINK_FIRST:
                if ($pagination->more_data_exist) {
                    array_pop($models);
                }
                break;
            case ApifonicaPagination::LINK_PREV:
                $models = array_reverse($models);
                if ($pagination->more_data_exist) {
                    array_shift($models);
                }
                break;
            case ApifonicaPagination::LINK_NEXT:
                if ($pagination->more_data_exist) {
                    array_pop($models);
                }
                break;
            default:
                $msg = "Failed pagination (processAfterFetchedModels). Given data = ";
                $msg = $msg. "Pag_direction = ". VarDumper::export($pagination->pag_direction);
                $msg = $msg. "Models count = ". VarDumper::export(count($models));
                \Yii::error($msg, __METHOD__);
                throw new ApicoServerErrorHttpException();
        }
        return $models;
    }

    /**
     * Check exist more Models than param "limit". Init param $pagination->more_data_exist.
     * @param $models array of Models that was fetched for pagination.
     */
    private function checkExistMoreModels($models)
    {
        /** @var \frontend\modules\v2\component\ApifonicaPagination $pagination */
        $pagination = $this->getPagination();
        $limit = $pagination->getLimit() + 1; //Check:Can we get MORE models. Should we create link "NEXT" or "PREV"

        if (count($models) == $limit) {
            $pagination->more_data_exist = true;
        } else {
            $pagination->more_data_exist = false;
        }
    }

    /**
     * Extract last and first key from fetched models
     * and return array of request QueryParams.
     * @param ActiveRecord[] $models
     * @return array of request QueryParams.
     */
    public function prepareRequestParams($models)
    {
        /** @var @var \yii\web\Request  $request */
        $request = \Yii::$app->getRequest();
        $params = $request->getQueryParams();

        /** @var \frontend\modules\v2\component\ApifonicaPagination $pagination */
        $pagination = $this->getPagination();
        $key= $pagination->pagination_key;

        $first_model = $models[0];
        $last_model = end($models);
        $params = ArrayHelper::merge(
            $params,
            ['prev' => (string)$first_model->$key, 'next' => (string)$last_model->$key]
        );

        return $params;
    }
}
