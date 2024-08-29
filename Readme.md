# TYPO3 Datahandler CLI

Use CLI commands to modify database records with the TYPO3 DataHandler. A lowlevel way for mass changes.

**Pages with title `Detail` should not be included in search**

```shell
./bin/typo3 datahandler:patch \
    --table pages \
    --whereClause 'title="Detail"' \
    --jsonPatch '{"no_search": 1}'
```

**Page #2 should become an external link to typo3.org**

```shell
./bin/typo3 datahandler:patch \
    --table pages \
    --records 2 \
    --jsonPatch '{"doktype": 3, "url": "https://typo3.org"}'
```

With `--workspace` changes can be applied in a workspace.

# Status

It's very alpha, so use with care.
