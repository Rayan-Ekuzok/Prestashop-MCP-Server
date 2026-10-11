# Checking the connection to the shop

Use this before concluding that the shop has no data, and whenever another
tool fails in a way that could mean either "nothing matches" or "nothing is
connected".

## Tools

- `somcp_ping`

## Workflow

1. Call `somcp_ping`.
2. Read back, in the merchant's own words: the shop that answered, the
   PrestaShop version, the account the connection acts under, and the currency
   and time zone the other tools will express their answers in.
3. On a multistore installation, say which shops this connection cannot reach.
   That is not a fault - one connection belongs to one shop - but a merchant
   looking for an order that is not there needs to know it.

## What the answer means

The account named in the answer is the one every other tool acts as. Its back
office permissions decide which tools exist at all: a connection that shows
fewer tools than the merchant expected is almost always an account question,
not a shop question.

The currency and time zone are the ones the shop is set to. Amounts and dates
in every other answer are in those, and relative periods like "yesterday" are
worked out against that time zone.

## Do not use when

- The merchant asks about orders, statuses or anything in the shop's data.
  This tool reads none of it.
- A tool returned an empty list. An empty list is an answer; the connection
  worked.

## Good triggers

- "Est-ce que tu es connecté à ma boutique ?"
- "Which shop are you looking at?"
- "Why can't you see my orders?"
- "Quel compte utilises-tu ?"
