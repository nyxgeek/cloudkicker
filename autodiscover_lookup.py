#!/usr/bin/env python3

import argparse
import requests
import xml.etree.ElementTree as ET
import re



def print_banner():
    print("+--------------------------------------------------------------------------------------+")
    print("+--------------------------------------------------------------------------------------+")
    print("+------                                                                          ------+")
    print("+------                                                                          ------+")
    print("+------           autodiscover_lookup.py  -  query autodiscover service          ------+")
    print("+------                                                                          ------+")
    print("+------                     @nyxgeek - @trustedsec - 2025.06                     ------+")
    print("+------                                                                          ------+")
    print("+------                                                                          ------+")
    print("+------                                                                          ------+")
    print("+--------------------------------------------------------------------------------------+")
    print("+--------------------------------------------------------------------------------------+")
    print("\n\n")

def do_autodiscover_lookup(domain, environment, output_override):
    if environment == 'gcc':
        autodiscover_host = 'autodiscover-s.office365.us'
        host_suffix = 'us'
    else:
        autodiscover_host = 'autodiscover-s.outlook.com'
        host_suffix = 'com'

    url = f"https://{autodiscover_host}/autodiscover/autodiscover.svc"

    headers = {
        'Content-Type': 'text/xml; charset=utf-8',
        'SOAPAction': 'http://schemas.microsoft.com/exchange/2010/Autodiscover/Autodiscover/GetFederationInformation',
        'User-Agent': 'AutodiscoverClient',
        'Accept-Encoding': 'identity'
    }

    xml_body = f'''<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:exm="http://schemas.microsoft.com/exchange/services/2006/messages"
xmlns:ext="http://schemas.microsoft.com/exchange/services/2006/types"
xmlns:a="http://www.w3.org/2005/08/addressing"
xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
xmlns:xsd="http://www.w3.org/2001/XMLSchema">
<soap:Header>
    <a:Action soap:mustUnderstand="1">http://schemas.microsoft.com/exchange/2010/Autodiscover/Autodiscover/GetFederationInformation</a:Action>
    <a:To soap:mustUnderstand="1">https://{autodiscover_host}/autodiscover/autodiscover.svc</a:To>
    <a:ReplyTo>
        <a:Address>http://www.w3.org/2005/08/addressing/anonymous</a:Address>
    </a:ReplyTo>
</soap:Header>
<soap:Body>
    <GetFederationInformationRequestMessage xmlns="http://schemas.microsoft.com/exchange/2010/Autodiscover">
        <Request>
            <Domain>{domain}</Domain>
        </Request>
    </GetFederationInformationRequestMessage>
</soap:Body>
</soap:Envelope>'''

    try:
        response = requests.post(url, headers=headers, data=xml_body, timeout=10)
        response.raise_for_status()
    except requests.exceptions.RequestException as e:
        print(f"[!] Error: {e}")
        return

    print(f"[+] Autodiscover response for domain: {domain}\n")

    try:
        root = ET.fromstring(response.content)
        domains = root.findall('.//{http://schemas.microsoft.com/exchange/2010/Autodiscover}Domain')
        if not domains:
            print("[!] No <Domain> entries found in response.")
            return

        domain_list = []
        mail_tenant = None
        fallback_tenant = None
        tenants_set = set()

        for d in domains:
            domain_text = d.text.strip().lower()
            domain_list.append(domain_text)

            mail_match = re.search(r'([a-z0-9\-]+)\.mail\.onmicrosoft\.' + re.escape(host_suffix), domain_text)
            fallback_match = re.search(r'([a-z0-9\-]+)\.onmicrosoft\.' + re.escape(host_suffix), domain_text)

            if mail_match and not mail_tenant:
                mail_tenant = mail_match.group(1)

            # Only add to tenants_set if NOT a .mail.onmicrosoft
            if fallback_match and not re.search(r'\.mail\.onmicrosoft\.', domain_text):
                tenants_set.add(fallback_match.group(1))
                if not fallback_tenant:
                    fallback_tenant = fallback_match.group(1)

        if mail_tenant:
            tenant_name = mail_tenant
            print(f"[+] Selected tenant from '.mail.onmicrosoft': {tenant_name}")
        elif fallback_tenant:
            tenant_name = fallback_tenant
            print(f"[+] Selected tenant from '.onmicrosoft': {tenant_name}")
        else:
            print("[!] Could not extract tenant name (no .onmicrosoft. domain found)")
            return

        # Determine output filenames
        if output_override:
            outfile_domains = output_override
        else:
            outfile_domains = f'domains.{tenant_name}.txt'

        outfile_tenants = f'tenants.{tenant_name}.txt'

        # Output domain list
        print(f"[+] Found domains:")
        for d in domain_list:
            print(f" - {d}")

        with open(outfile_domains, 'w') as f:
            for d in domain_list:
                f.write(d + "\n")

        print(f"\n[+] Saved domains to '{outfile_domains}'")

        # Output tenants list (only clean .onmicrosoft ones)
        clean_tenants = sorted(tenants_set)
        print(f"[+] Tenant list: {len(clean_tenants)} unique tenants")

        with open(outfile_tenants, 'w') as f:
            for tenant in clean_tenants:
                f.write(tenant + "\n")

        print(f"[+] Saved tenants to '{outfile_tenants}'")

    except ET.ParseError as e:
        print(f"[!] Failed to parse response XML: {e}")

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Perform Autodiscover FederationInformation lookup")
    parser.add_argument("-d", "--domain", required=True, help="Domain to lookup (e.g. contoso.com)")
    parser.add_argument("-e", "--environment", choices=["common", "gcc"], default="common", help="Environment (default: common)")
    parser.add_argument("-o", "--output", help="Output filename for domains (optional, default: domains.<tenant>.txt)")

    args = parser.parse_args()
    print_banner()
    do_autodiscover_lookup(args.domain, args.environment, args.output)
