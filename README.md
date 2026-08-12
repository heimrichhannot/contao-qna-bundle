# Contao Q&A Bundle

Event question-and-answer sessions for Contao 5.7.

## Configuration

```yaml
contao_qna:
    polling_interval: 2500
    max_question_length: 500
    question_cooldown: 20
```

These are technical limits only. Session assignments are stored in the Q&A
records and are not global configuration.

## Session-control authorization

Controllers that start or stop a session must check
`QnaSessionControlVoter::ATTRIBUTE` (`QNA_SESSION_CONTROL`). The bundle voter
allows every authenticated Contao front end member by default.

Host projects can tighten this rule without forking the bundle by replacing
the service identified by
`HeimrichHannot\QnaBundle\Security\Voter\QnaSessionControlVoter` with their own
voter implementation. The replacement must support the same attribute and be
registered as a Symfony security voter (autoconfiguration does this by
default).
