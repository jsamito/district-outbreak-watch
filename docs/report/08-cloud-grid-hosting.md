# 8. Cloud, grid and cluster computing, and hosting

## 8.1 Three ways of sharing computing power

A **cluster** joins similar computers in one place to act as one machine. A **grid** joins computers owned by different organisations in different places so that a large job is split across them. **Cloud computing** provides computing on demand over the network, with five features: on-demand self-service, broad network access, resource pooling, rapid elasticity and measured service [1].

| | Cluster | Grid | Cloud |
|---|---|---|---|
| Scalability | Add machines, slowly | Very large, across partners | Minutes, up or down |
| Cost | High fixed, low running | Shared; mostly partner time | Pay for use; can grow unnoticed |
| Virtualisation | Optional | Common | Core |
| Lock-in | Low | Low, but tied to the grid's tools | Real: proprietary services, transfer cost |
| Best use | One organisation's steady heavy load | Large jobs that split into pieces | Variable or growing services |

The cloud has three service models [1]: **IaaS** rents virtual machines and networks and the customer runs everything above them; **PaaS** also runs the operating system and runtime so the customer supplies the application; **SaaS** is a finished application used in a browser. The more the provider runs, the less the customer does and controls.

**Grid in health research.** The WISDOM project used the EGEE grid in 2005 to test over 46 million compounds against malaria parasite proteins on about 1,700 computers in 15 countries at once [8][9]. Each compound can be tested independently, which is why a grid fits; a dashboard has no such job.

## 8.2 The health questions

**Whose law, where is the data?** Uganda's Data Protection and Privacy Act 2019 treats health status as special personal data, and section 19 requires a Ugandan controller or processor that stores or processes personal data abroad to ensure equivalent protection there or the person's consent [5]. A law-firm summary reports enforcement of this against a large foreign provider [6]. I would keep identifiable health data in Uganda or a country with equivalent protection, and record where each system is hosted. This project's public site holds only national totals.

**Agreements.** Under US rules a cloud provider holding health information for a covered organisation must sign a business associate agreement even if it cannot read the data, and a service level agreement is no substitute [7]. HIPAA does not bind a Ugandan ministry, but partners often ask for equivalent terms. Before real data is loaded, the client should hold a signed agreement naming the data, region, security measures and what happens on a breach or at contract end.

**Audit, backups, recovery.** The application keeps an audit log, encrypts raw notification payloads and has no case export. The host must add nightly encrypted backups kept off the server for 14 days, a quarterly restore test and a written recovery target (for example, back within four hours with at most a day's data lost). The deployment guide sets these out.

## 8.3 Recommendation

**Now (hundreds of users):** one small virtual machine (IaaS) in an African data centre running web server, application and MySQL, with HTTPS and HSTS, nightly backups to a second location and an uptime check. It is cheap and simple and keeps data in one known place; Laravel and MySQL run on any provider, so lock-in is low.

**At ten times the usage:** separate the parts. A managed MySQL service with automatic backups and a standby; two or more application servers behind a load balancer; shared session and cache storage; the scheduler on its own small machine; static assets through a CDN; and monitoring with alerts. The application keeps sessions and cache in the database and uses standard components, so this needs no rewrite.

**Where.** For government use, first choice is the NITA-U National Data Centre and planned government cloud, which reports hosting over 300 government applications [13]. Otherwise use a commercial African region: Amazon, Microsoft and Google each run regions in South Africa [10][11][12]. Check the current region list and whether the provider will sign data-processing terms. A region outside Africa should hold only non-identifiable data, with a documented legal basis under section 19 [5].
