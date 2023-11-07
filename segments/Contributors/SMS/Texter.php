<?php

namespace Contributors\SMS;

use Bones\Skeletons\Supporters\AutoMethodMap;
use Bones\Str;
use Bones\SMSException;

class Texter extends AutoMethodMap
{
    protected $to;
    protected $from;
    protected $body;

    public function __send()
    {
        $this->validate();

        $via = setting('alert.sms.via', null);
        
        if (empty($via)) {
            throw new SMSException('SMS: No driver configuration found in the system to send an sms');
        }

        if (strtolower($via) == 'twilio') {

            $from = setting('alert.sms.twilio.from_number');

            if (!empty($this->from))
                $from = $this->from;

            $this->viaTwilio($from);

        } else {
            throw new SMSException('SMS: '.$via.' driver configuration not supported in the system to send an sms');
        }
    }

    public function __validate()
    {
        if (empty($this->to)) {
            throw new SMSException('SMS: must have atleast one receipient to send an sms to');
        }

        if (empty($this->body)) {
            throw new SMSException('SMS: body can not be empty');
        }
    }

    public function __to($to)
    {
        $this->to = $to;
        return $this;
    }

    public function __from($from)
    {
        $this->from = $from;
        return $this;
    }

    public function __setTwilio($twilio_details = [])
    {
        $this->twilio = $twilio_details;
        return $this;
    }

    public function __getTwilioAttr($attr)
    {
        if (!empty($this->twilio)) {
            return (!empty($this->twilio[$attr])) ? $this->twilio[$attr] : null;
        }

        return null;
    }

    public function __body($body)
    {
        $this->body = $body;
        return $this;
    }

    public function __template($view, $data = [])
    {
        $this->body = content($view, $data);
        return $this;
    }

    public function __viaTwilio($from)
    {
        $account_sid = (!empty($this->getTwilioAttr('account_sid'))) ? $this->getTwilioAttr('account_sid') : setting('alert.sms.twilio.account_sid', '');

        $auth_token = (!empty($this->getTwilioAttr('auth_token'))) ? $this->getTwilioAttr('auth_token') : setting('alert.sms.twilio.auth_token', '');

        $api_endpoint = (!empty($this->getTwilioAttr('api_endpoint'))) ? $this->getTwilioAttr('api_endpoint') : rtrim(setting('alert.sms.twilio.api_endpoint', 'https://api.twilio.com/2010-04-01/'), '/');

        $api_endpoint .= "/Accounts/". $account_sid;

        $from = (!empty($this->getTwilioAttr('from_number'))) ? $this->getTwilioAttr('from_number') : $from;

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $api_endpoint ."/Messages.json");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "Body=" . urlencode($this->body) . "&From=" . $from . "&To=" . $this->to);
        curl_setopt($ch, CURLOPT_USERPWD, $account_sid . ':' . $auth_token);

        $headers = array();
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new SMSException(curl_error($ch));
        }

        curl_close($ch);

        if (Str::isJson($result)) {
            $response = json_decode($result);
            if (empty($response->sid)) {
                throw new SMSException('[Twilio Error - ' . $response->message.']');
            }
        } else {
            throw new SMSException('Oops! Technical issue occured!');
        }

        return true;
    }

}