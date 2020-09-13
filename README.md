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

- The second version after underscore in zip name is used prismjs version.

#### For Joomla update server
- Create new release with new tag.
- Get download link for new `dist/plg_blahaba_blubber...zip` **from newly created tag branch** and add to release description and update server XML.
