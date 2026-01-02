#!/usr/bin/env node
/* eslint-disable no-console */
const fs = require('fs');
const path = require('path');

function parseArgs(argv) {
  const args = {
    count: 5,
    prefix: 'test',
    domain: 'example.com',
    out: '',
  };

  for (let i = 2; i < argv.length; i += 1) {
    const arg = argv[i];
    if (arg === '--count' && argv[i + 1]) {
      args.count = Number.parseInt(argv[i + 1], 10);
      i += 1;
    } else if (arg === '--prefix' && argv[i + 1]) {
      args.prefix = argv[i + 1];
      i += 1;
    } else if (arg === '--domain' && argv[i + 1]) {
      args.domain = argv[i + 1];
      i += 1;
    } else if (arg === '--out' && argv[i + 1]) {
      args.out = argv[i + 1];
      i += 1;
    }
  }

  if (!Number.isFinite(args.count) || args.count < 1) {
    args.count = 1;
  }

  return args;
}

function generateEmails(count, prefix, domain) {
  const timestamp = Date.now();
  const emails = [];
  for (let i = 0; i < count; i += 1) {
    const random = Math.floor(Math.random() * 1000);
    emails.push(`${prefix}-${timestamp}-${i}-${random}@${domain}`);
  }
  return emails;
}

const args = parseArgs(process.argv);
const emails = generateEmails(args.count, args.prefix, args.domain);
const output = emails.join('\n') + '\n';

if (args.out) {
  const outPath = path.resolve(process.cwd(), args.out);
  fs.writeFileSync(outPath, output, 'utf8');
  console.log(`Wrote ${emails.length} emails to ${outPath}`);
} else {
  process.stdout.write(output);
}
