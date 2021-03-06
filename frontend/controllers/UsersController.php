<?php

namespace frontend\controllers;

use frontend\models\forms\SettingsForm;
use frontend\models\UserHasFiles;
use frontend\models\City;

use frontend\models\File;
use frontend\models\User;
use frontend\models\forms\UsersFilter;
use frontend\models\forms\UsersSorting;
use Throwable;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\Exception;
use yii\helpers\FileHelper;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

class UsersController extends SecuredController
{
    const PER_PAGE = 5;

    /**
     * Shows a page with the users list.
     * @param string $sort
     * @return string
     */
    public function actionIndex($sort = UsersSorting::SORT_RATING): string
    {
        $usersFilter = new UsersFilter();
        $usersSorting = new UsersSorting();

        $query = User::find()
            ->join('INNER JOIN', 'user_has_skill as s', 's.user_id = user.id')
            ->distinct()
            ->with(['reviews', 'skills']);

        if (Yii::$app->request->getIsPost()) {
            $request = Yii::$app->request->post();

            if ($usersFilter->load($request) && $usersFilter->validate()) {
                $query = $usersFilter->applyFilters($query);
            }
        }

        $query = $usersSorting->applySorting($query, $sort);

        $provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => self::PER_PAGE
            ]
        ]);

        $users = $provider->getModels();

        return $this->render('index',
            [
                'users' => $users,
                'usersFilter' => $usersFilter,
                'usersSorting' => $usersSorting,
                'pages' => $provider->getPagination()
            ]
        );
    }

    /**
     * Shows a page with the user.
     * @param int $id
     * @return string
     * @throws NotFoundHttpException
     */
    public function actionView(int $id): string
    {
        $user = User::findOne($id);

        if (!$user) {
            throw new NotFoundHttpException("Пользователь с ID $id не найден");
        }

        ++$user->profile_read;
        $user->save(false, ['profile_read']);

        return $this->render('view', [
            'user' => $user,
            'cities' => City::find()->asArray()->all(),
            'inFavorites' => $user->isInFavorites()
        ]);
    }

    /**
     * Adds or removes the user from favorites.
     * @param int $id
     */
    public function actionToggleFavorites(int $id)
    {
        $user = User::findOne(['id' => $id]);
        $user->toggleFavoriteUser();

        $this->redirect(['users/view', 'id' => $user->id]);
    }

    /**
     * Shows the settings page.
     * @return string
     * @throws Throwable
     * @throws \yii\base\Exception
     */
    public function actionSettings(): string
    {
        $session = Yii::$app->session;

        $src = Yii::getAlias('@webroot/uploads/user') . Yii::$app->user->id;
        FileHelper::createDirectory($src);

        if (Yii::$app->request->isAjax) {

            $files = UploadedFile::getInstancesByName('file');
            $sessionFiles = $session['files'];

            foreach ($files as $file) {
                try {
                    $file->saveAs($src . '/' . $file->name);

                } catch (Throwable $e) {
                    throw $e;
                }
                $sessionFiles[] = $file->name;
            }

            $session['files'] = $sessionFiles;
            return $this->asJson(['answer' => 'OK', 'files' => $session['files']]);

        }

        $settingsForm = new SettingsForm();

        if (Yii::$app->request->isPost) {

            if ($settingsForm->load(Yii::$app->request->post()) && $settingsForm->save()) {
                if (!empty($session['files'])) {

                    $user = User::findOne(Yii::$app->user->id);
                    $transaction = Yii::$app->db->beginTransaction();
                    try {
                        $oldFiles = $user->files;
                        foreach ($oldFiles as $file) {
                            $file->delete();
                        }
                        UserHasFiles::deleteAll(['user_id' => $user->id]);

                        foreach ($session['files'] as $file) {
                            $newFile = new File();
                            $newFile->name = $file;
                            $newFile->src = 'uploads/user' . Yii::$app->user->id;
                            if (!$newFile->save()) {
                                throw new Exception('Couldn\'t save a file record');
                            }

                            $relation = new UserHasFiles();
                            $relation->user_id = Yii::$app->user->id;
                            $relation->file_id = $newFile->id;

                            if (!$relation->save()) {
                                throw new Exception('Couldn\'t save a relation the user to the file');
                            }
                        }
                        $transaction->commit();
                    } catch (Throwable $e) {
                        $transaction->rollBack();
                        throw $e;
                    }
                    unset($session['files']);
                }

                $this->redirect(['users/view', 'id' => Yii::$app->user->id]);
            }

        }
//      на случай, если пользователь покидал страницу без сохранения изменений
        if (!empty($session['files'])) {
            unset($session['files']);
        }

        return $this->render('settings', [
            'settingsForm' => $settingsForm
        ]);
    }

}

