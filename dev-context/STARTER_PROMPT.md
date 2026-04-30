# Starter Prompt — Generic Handover Template

Copy the block below, fill in every `[PLACEHOLDER]`, and paste it at the start of a new session with any LLM.

---

```
You are picking up an ongoing project. Here is the context you need before we start.

Project name: [PROJECT NAME]
What it is: [ONE SENTENCE — what the product does and who it is for]
My role: [e.g. founder, developer, designer, product manager]
Your role today: [e.g. developer, advisor, writer, architect]

The developer context file is at:
[ABSOLUTE PATH TO DEV_CONTEXT.md]

Read that file first. It has three sections:
1. Recent decisions — what has been locked in, do not revisit
2. Current work — the feature or task we are in the middle of
3. Remaining steps — everything left to build, in order

Also read:
/Users/gg1900/coding/waais-website/dev-context/CURRENT_STATE.md

Before touching anything:
1. Confirm you have read the file
2. Confirm you have read CURRENT_STATE.md
3. Check that all file paths mentioned in both files still exist on disk
4. Summarize in 3–4 sentences: what the project is, where we left off, and what comes next
5. Flag anything that looks outdated, broken, or missing

Then wait for my instruction.
```

---

## Filled-in example (WAAIS project)

```
You are picking up an ongoing project. Here is the context you need before we start.

Project name: WAAIS — Wharton Alumni AI Studio platform
What it is: A community platform for Wharton alumni working in AI, with a public website, member dashboard, and Discourse forum replacing an existing WhatsApp group.
My role: Founder
Your role today: Developer

The developer context file is at:
/Users/gg1900/coding/waais-website/dev-context/DEV_CONTEXT.md

Read that file first. It has three sections:
1. Recent decisions — what has been locked in, do not revisit
2. Current work — the feature or task we are in the middle of
3. Remaining steps — everything left to build, in order

Also read:
/Users/gg1900/coding/waais-website/dev-context/CURRENT_STATE.md

Before touching anything:
1. Confirm you have read the file
2. Confirm you have read CURRENT_STATE.md
3. Check that all file paths mentioned in both files still exist on disk
4. Summarize in 3–4 sentences: what the project is, where we left off, and what comes next
5. Flag anything that looks outdated, broken, or missing

Then wait for my instruction.
```
