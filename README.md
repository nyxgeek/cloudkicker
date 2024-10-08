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

### notes

This can take a few seconds (10~) to load, especially against larger organizations.

If you want to look up the tenant name, supply it as a ```.onmicrosoft.com``` address (e.g., contoso.onmicrosoft.com)

> [!IMPORTANT]
> Don't expose thise externally. By default, the page will check to see if it's protected by basic auth and if not, if it is reachable externally, it will not run. To allow access externally you must set the ```$disable_safety_checks``` variable to ```'True'``` in the php file.
> To restrict access, I recommend configuring basic auth and HTTPS, or restricting by IP address. For information on configuring basic auth, see the [wiki](https://github.com/nyxgeek/cloudkicker/wiki)
