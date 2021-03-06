<?php


namespace frontend\models\forms;


use frontend\models\Skill;
use TaskForce\models\TaskStatus;
use yii\base\Model;
use yii\db\ActiveQuery;
use yii\db\Query;

/**
 * This is the model class for form "usersFilter".
 *
 * @property int[] $skills;
 * @property string[] $additional;
 * @property string $search;
 */
class UsersFilter extends Model
{
    public ?array $skills = [];
    public ?array $additional = [];
    public string $search = '';

    const HALF_AN_HOUR = 1800;

    const ADDITIONAL = [
        self::ADDITIONAL_AVAILABLE => 'Сейчас свободен',
        self::ADDITIONAL_ONLINE => 'Сейчас онлайн',
        self::ADDITIONAL_WITH_RESPONSES => 'Есть отзывы',
        self::ADDITIONAL_IN_FAVORITES => 'В избранном',
    ];

    const ADDITIONAL_AVAILABLE = 'available';
    const ADDITIONAL_ONLINE = 'online';
    const ADDITIONAL_WITH_RESPONSES = 'withResponses';
    const ADDITIONAL_IN_FAVORITES = 'inFavorites';

    public function rules()
    {
        return [
            [
                ['skills'],
                'exist',
                'allowArray' => true,
                'targetClass' => Skill::class,
                'targetAttribute' => 'id'
            ],
            [
                ['additional'],
                'in',
                'range' => array_keys(self::ADDITIONAL),
                'allowArray' => true,
            ],
            [
                ['search'],
                'string',
                'max' => 128
            ],
            [
                ['search'],
                'trim'
            ],
        ];
    }


    /**
     * Gets additional fields.
     *
     * @return array
     */
    public static function getAdditionalFields(): array
    {
        return self::ADDITIONAL;
    }


    /**
     * Applies form filters.
     *
     * @param ActiveQuery $query
     * @return  ActiveQuery
     */
    public function applyFilters(ActiveQuery $query) : ActiveQuery
    {

        if (!empty($this->skills)) {
            $query
                ->join('INNER JOIN', 'user_has_skill', 'user_has_skill.user_id = user.id')
                ->andWhere(['user_has_skill.skill_id' => $this->skills]);
        }

        if (!empty($this->additional)) {

            if (in_array(self::ADDITIONAL_AVAILABLE, $this->additional)) {
                $query
                    ->join('LEFT JOIN',
                        'task as tsk', 'tsk.contractor_id = user.id AND tsk.status = :pending',
                        [':pending' => TaskStatus::PENDING]
                    )
                    ->andWhere(['tsk.id' => null]);
            }
            /* SELECT DISTINCT user.*, tsk.*
               FROM `user`
               INNER JOIN `user_has_skill` `s`
               ON s.user_id = user.id
               LEFT JOIN `task` `tsk`
               ON tsk.contractor_id = user.id
               AND `tsk`.`status` = 'PENDING'
               WHERE tsk.status IS null */

            if (in_array(self::ADDITIONAL_ONLINE, $this->additional)) {
                $query
                    ->andWhere(['>=', 'last_seen_at', $this->calculatePeriod(self::HALF_AN_HOUR)]);
            }

            if (in_array(self::ADDITIONAL_WITH_RESPONSES, $this->additional)) {
                $query
                    ->join('INNER JOIN', 'response', 'response.user_id = user.id');
            }

            if (in_array(self::ADDITIONAL_IN_FAVORITES, $this->additional)) {
                $query
                    ->join('INNER JOIN', 'favorite', 'favorite.favorite_id = user.id')
                    ->andWhere(['favorite.user_id' => \Yii::$app->user->id]);
            }
        }

        if ($this->search !== '') {
            $this->skills = [];
            $this->additional = [];
            $query->filterWhere(['LIKE', 'name', $this->search]);
        }

        return $query;
    }

    /**
     * Calculates time period.
     *
     * @param int $period
     * @return  string
     */
    private function calculatePeriod(int $period): string
    {
        return date('Y-m-d H:i:s',
            time() - $period);
    }
}
