# plg_content_prismhighlighterghsvs
 
## Work in (long) progress!!

## Build + new release
- Run composer if update for laminas/laminas.

- Run `ncu` and `ncu -u` to bump versions if updates for PrismJS/prism or zenorocha/clipboard.js. 
- Run npm install
- Adapt package.json (infos like version of Joomla plugin).
- run node build.js.

##### 
- New ZIP is in `/dist/`
- FYI: Packed files for this ZIP can be seen in `/package/`.

#### For Joomla update server
- Create new release with new tag.
- Get download link for new `dist/plg_blahaba_blubber...zip` **inside new tag branch** and add to release description and update server XML.

or

- You can add extension ZIP file to "Assets" list via Drag&Drop. See "Attach binaries by dropping them here or selecting them.".