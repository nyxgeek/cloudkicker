# cloudkicker
self-hosted Azure OSINT tool


### overview

We have been using this tool internally for the last few years. Decided to release it so you can all laugh at my poor code design and bad php ;)  

It is very similar to what @DrAzureAD's OSINT tool does (https://aadinternals.com/osint/). While this version lacks a few of the extra features, it is self-contained, requires no account, and can be hosted anywhere. It also comes with your choice of an AI background complete with misspelled words, or a custom-made cloudkicking roundhouse machine background.


<img src="https://github.com/user-attachments/assets/e0865d1d-1165-41c0-8631-322fe314d2b2" width="555">
 

<img src="https://github.com/user-attachments/assets/236c8088-2f94-4402-9d95-88017914e6e4" width="555">

### Azure checks
- [x] Tenants lookup
- [x] Custom Domains lookup
- [x] OneDrive hostname
- [x] SharePoint hostname
- [x] SharePoint/OneDrive Modern Auth enforcement
- [x] Mail Record Existence (indicates AD Sync)
- [x] ADFS Endpoint identification

### Installation - Setup

This is made to run on a LAMP server. Make sure you have Apache installed and PHP enabled.


### Installation - Securing with htaccess

CloudKicker won't let your perform lookups unless you have secured the endpoint with basic auth. The script will attempt to access itself on the public interface and see if it is prompted for auth.

This is just a minor safeguard to stop somebody's machine from being abused. You are responsible for securing your own hosted apps. If you want to override this default behavior, simply update the script variable ```$disable_safety_checks = False;``` to ```$disable_safety_checks = True;```, near the top of the script.

#### 1. Create an htpasswd file
Place this OUTSIDE of your web directory.

```
htpasswd -c /etc/apache2/.htpasswd username
```

This will prompt you to create a password.

#### 2. Create your htaccess file
Create this INSIDE of the web directory you want to protect (your cloudkicker folder)
```
nano /var/www/html/cloudkicker/.htaccess
```
Enter the following lines:
```
AuthType Basic
AuthName "Restricted Area"
AuthUserFile /etc/apache2/.htpasswd
Require valid-user
```
Be sure to update the AuthUserFile parameter to have the correct path to your .htpasswd file.

#### 3. Allow Override in Apache
You can skip this step if you want to see if it works without this. If you proceed to step #4 and it fails, then come back and do this.

Otherwise, verify that AllowOverride is enable. This can be found in your site's config file (e.g., ```/etc/apache2/sites-available/000-default.conf``` or ```/etc/apache2/sites-available/default-ssl.conf```).

```
<Directory /var/www/html>
    AllowOverride All
</Directory>
```

#### 4. Restart apache
```service apache2 restart```1

There are also tons of examples of this online if you get stuck.

### notes

This can take a few seconds (10~) to load, especially against larger organizations.

If you want to look up the tenant name, supply it as a ```.onmicrosoft.com``` address (e.g., contoso.onmicrosoft.com)

> [!IMPORTANT]
> Don't expose thise externally. By default, the page will check to see if it's protected by basic auth and if not, if it is reachable externally, it will not run. To allow access externally you must set the ```$disable_safety_checks``` variable to ```'True'``` in the php file.
> To restrict access, I recommend configuring basic auth and HTTPS, or restricting by IP address. For information on configuring basic auth, see the [wiki](https://github.com/nyxgeek/cloudkicker/wiki)
