<?php namespace Mvpasarel\ActivityLogSaaS;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;

class Activity extends Model {

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'activity_logs';

    /**
     * Get the user that the activity belongs to.
     *
     * @return object
     */
    public function user()
    {
        return $this->belongsTo(Config::get('auth.model'), 'user_id');
    }

    /**
     * Create an activity log entry.
     *
     * @param  mixed
     * @return boolean
     */
    public static function log($data = array())
    {
        if (is_object($data)) $data = (array) $data;
        if (is_string($data)) $data = array('action' => $data);

        if(!isset($data['userID'])) {
            $user = Auth::user();
            $data['userID'] = isset($user->id) ? $user->id : 0;
        }

        $appKey = Config::get('activity-log-saas::key');
        $appValue = Config::get('activity-log-saas::value');

        $activity = new static;
        $activity->user_id      = isset($data['userID'])   ? $data['userID']   : 0;
        $activity->$appKey      = isset($appValue) ? $appValue : 0;
        $activity->content_id   = isset($data['contentID'])   ? $data['contentID']   : 0;
        $activity->content_type = isset($data['contentType']) ? $data['contentType'] : "";
        $activity->action       = isset($data['action'])      ? $data['action']      : "";
        $activity->description  = isset($data['description']) ? $data['description'] : "";
        $activity->details      = isset($data['details'])     ? $data['details']     : "";

        //set action and allow "updated" boolean to replace activity text "Added" or "Created" with "Updated"
        if (isset($data['updated'])) {
            if ($data['updated']) {
                $activity->description = str_replace('Added', 'Updated', str_replace('Created', 'Updated', $activity->description));
                $activity->action = "Updated";
            } else {
                $activity->action = "Created";
            }
        }
        if (isset($data['deleted']) && $data['deleted'])
            $activity->action = "Deleted";

        //set developer flag
        $activity->developer  = !is_null(Session::get('developer')) ? true : false;

        $activity->ip_address = Request::getClientIp();
        $activity->user_agent = $_SERVER['HTTP_USER_AGENT'];
        $activity->save();
        return true;
    }

    /**
     * Get the name of the user.
     *
     * @return string
     */
    public function getName()
    {
        if (!(bool) $this->developer) {
            return Config::get('activity-log-saas::developerName');
        } else {
            $user = $this->user;
            if (empty($user))
                return "Unknown User";

            if (Config::get('activity-log-saas::usernameAsName')) {
                return $user->username;
            } else {
                if (Config::get('activity-log-saas::fullNameLastNameFirst')) {
                    return $user->last_name.', '.$user->first_name;
                } else {
                    return $user->first_name.' '.$user->last_name;
                }
            }
        }
    }

    /**
     * Get a shortened version of the user agent with title text of the full user agent.
     *
     * @return string
     */
    public function getUserAgentPreview()
    {
        return substr($this->user_agent, 0, 42) . (strlen($this->user_agent) > 42 ? '<strong title="'.$this->user_agent.'">...</strong>' : '');
    }

}