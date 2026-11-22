# 2. Browsers and search engines

## 2.1 Audit of an existing public dashboard

**Dashboard:** the WHO Immunization Data portal, global page (https://immunizationdata.who.int/) [26]. It shows reported cases of vaccine-preventable diseases, the same kind of content as this project.

**Method.** On 22 November 2026 I ran Lighthouse in Chrome 145 (mobile throttled, and desktop), read the network requests and console messages it records, and rendered the page in Firefox 158. All runs came from one slow laptop, so the numbers are for comparison only.

| Chrome, Lighthouse | Mobile | Desktop |
|---|---|---|
| Performance / accessibility | 39 / 85 | 56 / 82 |
| Best practices / SEO | 96 / 92 | 96 / 92 |
| Largest contentful paint | 4.2 s | 1.9 s |
| Total blocking time | 3,570 ms | 450 ms |

**Network.** 111 requests and about 3.3 MB: 24 scripts and 58 data calls, so the numbers wait for many separate fetches and the page was fully interactive only after about 18 s on the throttled profile. **Console.** Repeated failed loads (HTTP 404) from the content-delivery host: harmless to the user, but wasted time that can hide real faults. **Accessibility.** Low text contrast, images without alternative text, links without a name, and no `main` landmark.

**Firefox** showed the same layout, headline numbers and trend charts as Chrome; I saw no difference. In this environment I could only screenshot Firefox, so its network and console panels were not read, and Edge was not available (it shares Chrome's engine, so I expect the same, but that is not a measurement). Both remain to be done on a machine that has them.

**What it changed in my design:** chart code loads only when a chart scrolls into view, every chart has a text summary and data table, and console errors count as faults. On the same test, this project's signed-in pages score 85 to 100 for performance and 96 to 100 for accessibility.

## 2.2 How a search engine finds and ranks a page

A search engine works in three steps [2]. **Crawling:** programs follow links and sitemaps and download pages; they do not crawl everything they find. **Indexing:** the engine analyses what a page is about and stores it in a very large database. **Serving and ranking:** for each query it returns the pages it judges most relevant and of highest quality. Google says it cannot guarantee that a page is crawled, indexed or shown, and takes no payment for any of the three [2].

**Applied here.** The public pages have descriptive titles and descriptions, a clear heading structure, structured data (JSON-LD), an XML sitemap and a `robots.txt`.

**What must never appear in results:** dashboards, district and facility pages, case lists and analytics, the notification API, FHIR and export routes, the audit log and the sign-in page. They hold or lead to health information, and a listing would also advertise where to attack. Two layers protect them. Sign-in and area permission are the real protection, because `robots.txt` is a request to well-behaved crawlers and "not a form of access authorization" [3]. Second, these routes are `Disallow`ed and send `noindex`, so honest crawlers stay away. The public pages show national totals only, so even a crawler that reached them would learn nothing about a district.

## 2.3 Reflection: health information in search results

Search ranks relevance and popularity, not truth, and people trust the first results. In an observation study, Eysenbach and Köhler found that consumers looked at only the first few links; although they said they judged sites by source, none opened the "about us" or disclaimer pages, and few could say afterwards where an answer came from [4]. The study is small and from 2002, but it matters more in an outbreak, when WHO describes an "infodemic" of accurate and inaccurate information [16].

So a public page should answer the question people type, say who produced the numbers, give their date and limits, and link to its sources. This project does: every figure has a week and a small-number caution, a notice shows while sample data are loaded, and one page lists each dataset, licence and limit. A page cannot make itself rank first, but it can let a visitor judge how far to trust it.
