
# ![CC-PLUS logo](images/CC_Plus_Icon_thumb.png) CC-PLUS
CC­-PLUS is an open source software, community, and administrative tool set for usage statistics management that supports libraries and consortia with data­-driven decisions and effective stewardship of electronic resources.

This shareable platform enables consortia and member libraries to:

* establish and enhance proactive, community-­based approaches to usage data management among consortia, with global applicability;
* create staffing and cost efficiencies with flexible, shared infrastructure;
* increase libraries’ analytic capacity with flexible tools;
* support adherence to and use of COUNTER and NISO standards within the library, publisher, and consortial communities; and
* empower libraries and consortia to practice exemplary stewardship by making data­-informed decisions regarding investments in electronic resources.

The initial release of the CC­-PLUS platform was developed in partnership with PALCI (Partnership for Academic Library Collaboration & Innovation) and supported by LSTA grant-funding. The code repository and the documentation for the original (and deprecated) version of CC-Plus can be downloaded at: http://github.com/palcilibraries/CC-PLUS .

The Kentucky Virtual Library (http://kyvl.org) has built on the original work and is now leading development and maintenance of the system. Current releases are made available under an Apache 2.0 software license. The application uses library COUNTER credentials to harvest COUNTER-compliant (5.0 , 5.1) reports from major scholarly publishers. Reports are validated and stored and the system tracks and reports any problems with data harvests. Derivative usage reports can be generated through a dynamic interface corresponding to consortial and library needs.

CC-Plus is currently designed to run as a standalone web-based Laravel application connected to a MySQL database and a web server. It allows for multiple, or just a single, consortia to be managed within a host system. The report harvesting system uses the COUNTER protocol, and expects to receive valid and conformant COUNTER-5/5.1 usage reports.

The application is still under active development, but stable and periodic releases are now being released in GitHub.

[Latest Release](http://github.com/CPE-ITTeam/CCPLUS/releases/latest).

[Installation documentation is available here](installation.markdown).
