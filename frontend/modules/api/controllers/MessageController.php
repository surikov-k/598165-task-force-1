<?php


namespace frontend\modules\api\controllers;


use frontend\modules\api\resources\Message;
use frontend\modules\api\resources\Task;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\rest\ActiveController;
use yii\web\ForbiddenHttpException;
use yii\web\ServerErrorHttpException;

class MessageController extends ActiveController
{
    public $modelClass = Message::class;

    public function behaviors()
    {

        $behaviors = parent::behaviors();
        $behaviors['access'] = [
            'class' => AccessControl::class,
            'denyCallback' => function ($rule, $action) {
                return $this->asJson([]);
            },
            'rules' => [
                [
                    'allow' => true,
                    'roles' => ['@']
                ]
            ]
        ];
        return $behaviors;
    }


    public function actions()
    {
        $actions = parent::actions();
        unset($actions['create']);
        unset($actions['update']);
        unset($actions['view']);
        unset($actions['delete']);
        $actions['index']['prepareDataProvider'] = [$this, 'prepareDataProvider'];

        return $actions;
    }


    public function prepareDataProvider()
    {
        return new ActiveDataProvider([
            'query' => $this->modelClass::find()->andWhere(['task_id' => \Yii::$app->request->get('task_id')]),
            'pagination' => false
        ]);
    }


    public function actionCreate()
    {

        $request = \Yii::$app->request;

        $task = Task::findOne($request->post('task_id'));
        if (\Yii::$app->user->id !== $task->client_id && \Yii::$app->user->id !== $task->contractor_id) {
            throw new ForbiddenHttpException('You do not have permission to create this message ');
        }

        $message = new $this->modelClass;

        $message->text = $request->post('message');
        $message->task_id = $request->post('task_id');
        $message->user_id = \Yii::$app->user->getId();

        if ($message->save()) {
            $message->refresh();
            $response = \Yii::$app->getResponse();
            $response->setStatusCode(201);

        } elseif (!$message->hasErrors()) {
            throw new ServerErrorHttpException('Failed to create the object for unknown reason.');
        }

        return $message;
    }
}
