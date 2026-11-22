# 10. Emerging technology: AI-assisted analytics for surveillance

## 10.1 What it could mean

**AI-assisted analytics** means machine-learning models that forecast counts or flag unusual patterns, and language models that summarise reports or answer questions about the data. The promise is earlier warning from messy, late data and less time on routine summaries. The dashboard has a small version: a model chosen by rolling backtest (moving average, exponential smoothing or damped trend), its error shown beside a "same as last week" baseline, with intervals that never fall below zero.

**Benefits.** Models can read many signals at once (reports, lab results, page views, weather) and catch a rise a person scanning charts would miss. Drafting weekly bulletins frees district officers for response. Federated learning lets facilities train a shared model without moving patient records, which suits sensitive, tightly regulated data [22].

## 10.2 Risks

**Bias from the wrong target.** A widely used US algorithm for choosing patients for extra care used future health spending as a stand-in for need. Less is spent on Black patients with the same illness, so it under-rated their need; correcting it would raise the share of Black patients chosen from 17.7% to 46.5% [17]. The same trap appears if "reports received" stands in for "disease present": districts with weaker reporting would look healthier.

**Overconfidence in big data.** Google Flu Trends repeatedly overestimated flu; its reviewers blame "big data hubris" (treating big data as a replacement for careful measurement) and changes in the source system, and found that even three-week-old official data predicted flu better [18].

**Weak data and poor transfer.** Reviews of machine learning for outbreak forecasting keep finding fragmented, low-quality data, results that do not carry over to other places and models that cannot explain themselves [23][24]. A model trained on one district's reporting habits may fail in another.

**Privacy.** Supposedly anonymous health records can sometimes identify a person from a few details, a reason to avoid pooling record-level data; federated learning keeps records at source but needs its own safeguards [22].

**Equity and the digital divide.** WHO's 2021 guidance on AI for health puts inclusiveness and equity beside autonomy, safety, transparency and accountability [19]. Its 2024 guidance on large multi-modal models warns that the best models may be out of reach of poorer countries because of cost and the digital divide, and that building them is concentrated in a few large companies [20][21]. With about 28% of Ugandans online in 2025 [14], tools built on social media or smartphone signals describe the connected, mostly urban population, not the whole country.

## 10.3 Judgement for this project

AI belongs here only where it can be checked:
1. Keep simple, explainable models as the default, and require a new model to beat the baseline in a backtest on Ugandan data before it is shown (the dashboard already displays the comparison; making it a hard rule is a further step).
2. Use language models only to draft text from figures the system already holds, with a person approving every message, never to read case records or decide who is alerted.
3. Audit fairness by area: compare accuracy and missed signals between well-reporting and poorly-reporting districts, and publish the result [19].
4. Keep data in the country and consider federated training across districts before any central pooling [22].
5. Show what the model cannot see: reporting completeness beside every forecast, as the dashboard does.

Used this way, AI can lift a small surveillance team; used carelessly, it can make the districts with the weakest data look the safest.
