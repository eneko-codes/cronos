## 👨🏻‍💻 SETUP SERVER

For the web app to work, you will need to install a queue manager such as Supervisor in your Ngnx instance!

## ⛓️ SETUP API CONNECTIONS

### Odoo

#### It will return the following data in XML-RPC format.

- Employee details:
  - Name
  - Email
  - Odoo ID
- Employee calendar data (from the Calendar module):
  - Weekly work hours
  - Vacations

#### Connect Odoo to the web app:

- [x] Log into the admin account.
- [ ] On the top right part of the screen clik on "My Account".
- [ ] Click on the "Account security" tab.
- [ ] Under the "API Keys" section, generate a new key and copy it.
- [ ] Go to the Laravel project, open the .env (located in the root directory) and pass it in "ODOO_API_KEY".
- [ ] Set the "ODOO_URL" to the URL of your Odoo site (domain.odoo.com).

---

### Desktime

#### It will return the following data in JSON format:

- Employee details:
  - Name
  - Email
  - Desktime ID
- Employee remote work hours

#### Connect DeskTime to the web app:

- [x] Log into the admin account.
- [ ] On the left sidebar clik on the Settings dropdown and select "API".
- [ ] Under the "Introduction" tab, you will find "Your API Key", copy it.
- [ ] Go to the Laravel project, open the .env (located in the root directory) and pass it in "DESKTIME_API_KEY".
- [ ] Set the "DESKTIME_URL" to the URL of your DeskTime site.

---

### ProofHub

#### It will return the following data in JSON format:

- Employee details:
  - Name
  - Email
  - ProofHub ID
- Projects that the employee is participating in:
  - Projects
  - Tasks

#### Connect Proofhub to the web app:

- [x] Log in to the admin account.
- [ ] On the bottom left part of the screen, click on your account avatar, a dropdown will open. Select "API access".
- [ ] Copy the API Key.
- [ ] Go to the Laravel project, open the .env (located in the root directory) and pass it in "PROOFHUB_API_KEY".
- [ ] Set the "PROOFHUB_URL" to the URL of your DeskTime site.

---

> Once you are finished setting up all the API Connections, run these commands inside the Laravel root directory:
> `php artisan config:clear` > `php artisan cache:clear`

---
