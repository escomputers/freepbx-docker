# Sysadmin Hooks

These files are only usable with FreePBX Distro based machines.

FreePBX Distro, the FreePBX Sysadmin Module, and the sysadmin RPM together provide a secure privilege escalation service that permits authorized (signed) files to be run as root, even though they are not owned by the root user. This allows them to be updated without requiring system level updates to be performed.

Note that there are only a small number of whitelisted keys that can sign system hooks. If you want to design a system hook for your machine, please contact the developers via IRC (Freenode #freepbx-dev) or the FreePBX Forums (community.freepbx.org) and each request will be examined on a case by case basis.

An example of a system level hook is below, as a base64'd tgz.

    H4sIAKmBN1gAA+3V7Y+aSBgA8P08f8W0/dBevF3eBXvxElhQYBVRRNSmHxCGN3FAXlb0rz/c3Utz
    TdvLNe0ll+MXzYzzwDwgMzx1WRBp5rkp4ZYVKuJyT1SorMo4vGu/Nz8E2eqz7FPb+ryl+xx3QzEk
    xfEkybX9tseRzA0kf0z6b6vLyi0gvCmyrPrWcX8X/4+6vZKUsWZAc2xCSxsbigynimWJY+UpCFS3
    jN5DSxUpAH578w+1Z8AXowIhU1pDa2MtlSm04hC7VV0gOIpT9OdB8M335ZAziLMKumm7iGEVIehl
    uEK4KmEWtL/jEgZtllcQasFzjk+DsG0r95CjAvnwFFfRr08TuHmexp5bxRl+OeMUpykM3DiFj24a
    +88hF/vQ0SYTaMyWUFLgwjZefe99gA/tVQdx+BFU5xwNy3O7JQ/gERVlm2lIgah9EsMycmmuD9rd
    iZF/vdwhR404qS/INNduHPleeontzsNr5/Z5mrs8ykGB8mx4cHHtpuDDdTZUfgRE/dV3ABzCgUe3
    Hz4YcKzv8mjABbyAPJpmaYFCgod4liZ9zt35yOdozh0Ink/vKDdgdi5FCzS6/nEK9sEXFpq4tBcv
    a2z1fI/v4RjX5hg+0nfkHcXCd2PDJiYxrptfAIjnmieJiiSG4lia6xuDHVWimOnKSKWqjExHK2ai
    LEmTYJbyrKgb3e4vGk1DwTLGeqKEQC1LprZPAr9fhXNKlEYkbw0ow2RP5wuzt5Jabg6bAY7T0OdU
    /+Qf1xNX5h1hLHjrlBUIwAnFerzU+bygp2JCUX10mY42VTyvYpvRY5RuL5dTIh3PTRgJM4owcE8Y
    sN4uVNUlCh0fhIgvZOLIF9PBXp5NtvEqdAJhzKpqSK2SRVpn8nSSmws5kXa2tIxwzTYrL0PB2ZA8
    zHBgn1SH+7Du+ZG8sHo923ZkHGUka6YPRbB/IIht6QlLXTqYRTGT8WCtzxKrtJrZluR1MwzAdsX3
    Lsip9eOxVPx6rqxkzYkXM9er1XGVJ1FvvkNj0cPrieVcdHtfGU5krKKLpql8MRfBIiVXvEGXCT7O
    l2b+mG+i6XbrOIRMIJo6iCReb8gRMVmkMsLze7E/TR6yB/LI7iJZZM4m4KS1IR6xkZCOcTHwgnOP
    TUp6UuG5UbLRneNc0/Gh3kuMRSFGXyiX0g6px9M512pmRh5BmFymQr5fIuzaaoVIpXTZUN0ki2in
    +SNKo7iZJqyd0/gy6gVxM1DSXIyCnmuI97Q62QnAnB/bha7XIzLCWbOf2eeMmEi+mq0oR++bOm/M
    S0yY+7Vf0OeervQY7oGTbCby9wXR7HWwC9aRY7OkoeeKy6jp9DSxVyp9yYyE5tO99siUs9SRmgXb
    kGYd9ANqTu80nj5HcY6VJQKD+x7tzwPr3qgfGmu2FaYHFgwbesQ+7xPFkL+0S37C+//re//H5bgW
    ef5a179c/0mqz36q/yzT1n+Wp7r6/69484rYxZjYtbUA3jYAeVEGXy9qjGMcwrdPpeEaj/23T5Uu
    q6u8rqprsMogUR3yNvQ8+BoUB3gb/HUQxD78/bOhn7GMO51Op9PpdDqdTqfT6XQ6nU6n0+l0Op1O
    53/vD0Z/l+YAKAAA

Extract that file and then run "touch /usr/local/asterisk/incron/SYSTEM.testsig" which will run the test command as root.

Note that files in this directory are moved to $AMPWEBROOT/admin/modules/framework/hooks explicitly (See BMO::Install)
