/**
 * Build PDF from docs/คู่มือวิธีการเล่น.md
 * Usage: node scripts/build-manual-pdf.js
 */
const path = require('path')
const fs = require('fs')

async function main() {
  const root = path.join(__dirname, '..')
  const docsDir = path.join(root, 'docs')
  const mdFile = path.join(docsDir, 'คู่มือวิธีการเล่น.md')
  const cssFile = path.join(docsDir, 'pdf-style.css')
  const outFile = path.join(docsDir, 'คู่มือวิธีการเล่น.pdf')

  if (!fs.existsSync(mdFile)) {
    console.error('Markdown not found:', mdFile)
    process.exit(1)
  }

  const mdToPdf = require('md-to-pdf').mdToPdf

  const pdf = await mdToPdf(
    { path: mdFile },
    {
      basedir: docsDir,
      dest: outFile,
      stylesheet: [cssFile],
      pdf_options: {
        format: 'A4',
        printBackground: true,
        margin: { top: '18mm', right: '16mm', bottom: '20mm', left: '16mm' },
      },
      launch_options: {
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
      },
    }
  )

  if (!pdf) {
    console.error('PDF generation failed')
    process.exit(1)
  }

  const sizeKb = Math.round(fs.statSync(outFile).size / 1024)
  console.log('OK:', outFile)
  console.log('Size:', sizeKb, 'KB')
}

main().catch((err) => {
  console.error(err)
  process.exit(1)
})
