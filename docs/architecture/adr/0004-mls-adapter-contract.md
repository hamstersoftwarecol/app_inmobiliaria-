# ADR-0004: MLS provider adapter contract, disabled by default

* Status: Accepted · Date: 2026-10-10

## Context
NJMLS, GSMLS and Bright MLS may differ in protocol, authentication, field names and licence terms.
No licence or specification has been provided yet.

## Decision
`MLSProviderInterface` + capability descriptor; a generic RESO Web API adapter implements the RESO
standard; provider classes extend it but are flagged `SPECIFICATION_VERIFIED = false`.
`MLSConnectionManager` refuses production connections unless the global switch is on and a super
admin authorized the provider with an explicit licence confirmation. Display rules default to deny.

## Consequences
+ Pipeline and UI can be built and tested with synthetic fixtures; no unauthorized data use.
− Real behaviour per provider remains unverified until credentials and specs arrive (tracked as
  blocked in the backlog).
