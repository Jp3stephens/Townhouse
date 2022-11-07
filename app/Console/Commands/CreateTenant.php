<?php

namespace App\Console\Commands;

use App\Models\User;
use Hyn\Tenancy\Contracts\Repositories\CustomerRepository;
use Hyn\Tenancy\Contracts\Repositories\HostnameRepository;
use Hyn\Tenancy\Contracts\Repositories\WebsiteRepository;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Customer;
use Hyn\Tenancy\Models\Hostname;
use Hyn\Tenancy\Models\Website;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateTenant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:create {name} {email}';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates a tenant with the provided name and email address e.g. php artisan tenant:create jack jack@example.com';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $name = $this->argument('name');
        $email = $this->argument('email');

        if ($this->tenantExists($name, $email)) {
            $this->error("A tenant with '{$name}' and/or '{$email}' already exists!"); 
            return; 
        }
        $hostname = $this->registerTenant($name, $email); 
        app(Environment::class)->hostname($hostname); 

        // we will create a random secure password for our to-be admin
        $password = str_random(); 
        $this->addAdmin($name, $email, $password); 

        $this->info("Tenant '{$name}' is created and now accessible at {$hostname->fqdn}"); 
        $this->info("Admin '{$email} can log in using password {$password}"); 
    }
     
    private function tenantExists($name, $email)
    {
        return Customer::where('name', $name)->orWhere('email', $email)->exists();
    }

    private function registerTenant($name, $email)
    {
        $customer = new Customer;
        $customer->name = $name;
        $customer->email = $email;
        app(CustomerRepository::class)->create($customer);

        $website = new Website;
        $website->customer()->associate($customer);
        app(WebsiteRepository::class)->create($website);

        $hostname = new Hostname;
        $baseUrl = config('app.url_base'); 
        $hostname->fqdn = "{$name}.{$baseUrl}";
        $hostname->customer()->associate($customer); 
        app(HostnameRepository::class)->attach($hostname, $website);

        return $hostname;
    }

    private function addAdmin($name, $email, $password)
    {
        $admin = User::create(['name' => $name, 'email' => $email, 'password' => Hash::make($password)]);
        return $admin; 
    }
}
