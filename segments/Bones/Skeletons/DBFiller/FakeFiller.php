<?php

namespace Bones\Skeletons\DBFiller;

class FakeFiller
{
    protected $first_names = ['Jubeda', 'Gulam', 'Ayat', 'Mahira', 'Aarish', 'Yasmin', 'Fizza', 'Akbar', 'Ashik', 'Imdad', 'Abid', 'Asif', 'Jafar', 'Mansur', 'Irshad', 'Rehan', 'Hasan', 'Zaheer', 'Maisam'];
    protected $last_names = ['Gathamaniya', 'Manknojiya', 'Manasiya', 'Dhukka', 'Suna', 'Masiya', 'Meva', 'Memanjiya', 'Maknojiya', 'Sheru', 'Moriya', 'Banka'];
    protected $streets = ['Meva Vaas', 'New Majid', 'Maherpura', 'Maknojiya Vaas', 'Near Imambag'];
    protected $cities = ['Palanpur', 'Palanpur', 'Palanpur', 'Palanpur', 'Palanpur'];
    protected $states = ['GJ', 'GJ', 'GJ', 'GJ', 'GJ'];
    protected $job_titles = ['Software Engineer', 'Farmer', 'Doctor', 'Project Manager', 'UX Designer'];

    public function firstName()
    {
        return $this->first_names[array_rand($this->first_names)];
    }

    public function lastName()
    {
        return $this->last_names[array_rand($this->last_names)];
    }

    public function name()
    {
        return $this->firstName() . ' ' . $this->lastName();
    }

    public function age()
    {
        return rand(16, 74);
    }

    public function street()
    {
        return $this->streets[array_rand($this->streets)];
    }

    public function city()
    {
        return $this->cities[array_rand($this->cities)];
    }

    public function state()
    {
        return $this->states[array_rand($this->states)];
    }

    public function address()
    {
        return $this->street() . ', ' . $this->city() . ', ' . $this->state();
    }

    public function phoneNumber()
    {
        $area_code = mt_rand(100, 999);
        $prefix = mt_rand(100, 999);
        $line_number = mt_rand(1000, 9999);

        return $area_code . '-' . $prefix . '-' . $line_number;
    }

    public function url($resource_path = '')
    {
        $protocols = ['http', 'https'];
        $domains = ['wisencode.com', 'test.org', 'demo.net', 'sample.org'];
        $path = (!empty($resource_path)) ? '/' . $resource_path : '/home.html';

        $protocol = $protocols[array_rand($protocols)];
        $domain = $domains[array_rand($domains)];

        return $protocol . '://' . $domain . $path;
    }

    public function jobTitle()
    {
        return $this->job_titles[array_rand($this->job_titles)];
    }

    public function latitude()
    {
        return mt_rand(-90 * 10^6, 90 * 10^6) / 10^6;
    }

    public function longitude()
    {
        return mt_rand(-180 * 10^6, 180 * 10^6) / 10^6;
    }

    public function coordinates()
    {
        return [
            'latitude' => $this->latitude(),
            'longitude' => $this->longitude()
        ];
    }

    public function randomString($length = 8)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $string = '';

        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[mt_rand(0, strlen($characters) - 1)];
        }

        return $string;
    }

}