# Archon 3.21 live-instance survey (archon.library.illinois.edu) — for synthetic fixture design

Survey date 2026-10-22. 233 GET requests total (ihlc 77, libweb 53, rbml 39, ala 37, sousa2 27). Spacing was at least 1.2 s
between requests. Each instance had its own cookie jar. No forms, logins, admin pages, or `f=` URLs were requested.
This copy lives in `tests/research/live-survey/` (see README.md there). Curated responses are in `html/` (pages over 1 MB
are gzipped); the uncurated `raw/` responses were not committed. The helper scripts are in `scripts/`.
Note: 42 ihlc requests (#94–#135) were lost to an exponential probe for the last subjects page that never ended.
Because of the subjects pagination bug (see H), pages from 3 upward are never empty and always show "Next", even
page=109951162777600.

Instance keys: **ihlc** = /ihlc/ · **libweb** = /archives/?settheme=library_web&setrepositoryid=0 · **rbml** = /rbml/ ·
**ala** = /ala/ · **sousa2** = /archives/index.php?settheme=sousa2&setrepositoryid=2.

---------------------------------------------------------------------------------------------------------------------
## A. Instances, themes, template sets, routes, counts

| key | site title (og:site_name) | theme (css path) | template set (fingerprint) | DB |
|---|---|---|---|---|
| ihlc | Illinois History and Lincoln Collections (…Manuscript Collections Database) | `themes/ihlc/style.css?version=20261013` | **ihlc** ("Collection identifier", "Forms of Material", "Acquisition Source", "Descriptive note") | own DB |
| libweb | University of Illinois Archives (…Holdings Database) | `themes/library_web/style.css?v=20260925` | **illinois** ("Series Number:", "Acquired:", "Volume:", "Administrative History of Creating Unit", "Online Images / Records") | archives DB (all repos) |
| sousa2 | The Sousa Archives and Center for American Music | `themes/sousa2/style.css?v=20241220` | **illinois** (same as libweb) | archives DB, RepositoryID=2 |
| rbml | Rare Book & Manuscript Library… | `themes/rbml/style.css?v=20260812` | **rbml** ("ID:", "Extent:", "Restrictions:", "Rights:", "Collection Material Type:", box filter) | probably its own DB (IDs overlap; not verified) |
| ala | The American Library Association Archives | `themes/ala_redo/style.css?v=20240717` | **ala** ("Series Number:", "Volume:", "Administrative History", "Predominant Dates") | own DB |

- **Separate databases.** The same collection ID resolves to different records on different installs. archives `id=777` is "Departmental Correspondence, 1903-05, 1907-68" (8/7/1), while ihlc `id=777` is "Harlington Wood Jr. Papers". ala `id=8814` exists on ala, but on archives it returns an empty 200 page with no error text.
- **ID ranges seen.** Collections: ihlc 2–8324, archives 179–13433, rbml 10–1832, ala 6964–8918. Archives content IDs go up to about 229k. Classification IDs: ala's start at 3340; archives IDs seen were 1–334.
- **Theme template sets.** The ihlc and library_web themes set `$_REQUEST['templateset']="illinois"` in `header.inc.php`, but this happens after the template set is loaded, so it does not change which templates load for the current page. In practice the template set comes from `?templateset=` or CONFIG_CORE_DEFAULT_TEMPLATE_SET, with two per-record overrides in the repo code:
    - controlcard.php: a non-empty **Repository.TemplateSet** replaces the template set, even `?templateset=print|kardexcontrolcard`.
    - findingaid.php: a non-empty **Collection.TemplateSet** replaces it unless the request has exactly `templateset=EAD`. The check is case-sensitive, so `templateset=ead` is still overridden.
    - Live print and kardex views rendered for ihlc 777 and archives 4698, so those repositories probably have no TemplateSet. Whether any live records set these fields is not visible.
- **Live templates are customised beyond this repo.** These strings appear on the live sites but are not in the repo templates: "Submit request (Aeon)" (armarium.library.illinois.edu links), "Locations for this record series", "Subjects (links to similar materials)", "PDF Box/Folder List" (`#pdf-fa`), and the "Filter by location/box" table. Fixtures should target the repo templates, not the live HTML.
- **Repositories.** archives DB has repo 2 (Sousa) and the "University of Illinois Archives" repo; `setrepositoryid=0` means all repos. The other installs each show a single repository name: "Illinois History and Lincoln Collections", "Rare Book & Manuscript Library, University of Illinois at Urbana-Champaign", and "The American Library Association Archives".
- **Routes linked from pages** (full list with examples in `urls.txt`):
  - All installs: collections/collections (`&browse`, `&char=A|%23`, `&page=N`), collections/controlcard, collections/findingaid (`&q=`, `&rootcontentid=N#idN`, `#pdf-fa`), the print view (`&templateset=print&disabletheme=1` on controlcard and findingaid), creators/creators and creators/creator, subjects/subjects (`&browse&subjecttypeid=0&page=N`, `&subjecttypeid=1..10`, `&id=`), digitallibrary/digitallibrary, digitallibrary/digitalcontent, digitallibrary/thumbnails (`&collectionid=`, `&creatorid=`, `&thumbnailpage=N`), digitallibrary/getfile (`&preview=short|long`, only where files exist), core/search (`&subjectid=`, `&collectionid=`, `&languageid=2081`), and admin/core/login (not followed).
  - ihlc: the home navigation has no Record Groups link. `collections/classifications` exists but is empty. Has `index.php?f=searchmultiple`, `#identifiersearch` and core/index&f=privacy (none followed).
  - libweb, ala, rbml, sousa2: collections/classifications. libweb and ala also have core/index&f=pdfsearch (not followed) and core/register.
  - archives install (libweb and sousa2 pages): collections/eadlist ("List links to EAD files"). Requested directly on ihlc, libweb, rbml and ala, it returns **HTTP 500 with an empty body** on all 4. Also, on libweb, accessions/accession&id=.
  - rbml, ala, sousa2: collections/research&f=cart|email (research cart, not followed). rbml and ala lists have per-item cart icons.
  - Pre-existing production failure (checked 2026-10-23): `archives/index.php?p=accessions/accessions&settheme=sousa2&setrepositoryid=2` returns **HTTP 500 with an empty body**. `countAccessions()` (packages/accessions/lib/core/archon.inc.php) filters on `RepositoryID`, but tblAccessions_Accessions has no such column. This is not a PHP 8 regression; the PHP 8 crawl reports it as `Invalid column name 'RepositoryID'`.
  - **No "gallery" route exists.** The gallery is `digitallibrary/thumbnails`: 10 thumbnails per page, titles truncated to 27 characters plus "...".
- **Counts** (40 per page, found by bisecting `&browse&page=N`; "exact" means the last page was found):

| key | collections | creators | subjects (unique IDs in view-all) | digital content |
|---|---|---|---|---|
| ihlc | 1187 (exact) | ~1131 | 1756 | 89 |
| libweb (all repos) | 7260 (exact) | ~3120–3200 | 5894 | ~14,960–15,960 |
| sousa2 (repo 2) | ~272 | (global list, same as archives) | (global) | ~5,040–5,360 |
| rbml | ~314 | ~315 | 200 | ~3,320–3,480 |
| ala | ~1662 | ~504 | 4534 | ~3,960–4,200 |

  Subject counts are the IDs listed in view-all. The true total is probably one higher, because view-all drops its last row (see H).
  Some browse letters are empty: ihlc collections has no X; rbml collections has no #, O, Q, U, V or X; ala collections has no #, Q, X or Z; ihlc digital content uses only 9 letters.
- **Classifications.**
  - archives: 67 top-level "Campus Units", numbered 0–67 with no 42 or 47, three levels deep. Example: 26 → 26/20 Alumni → 26/20/37 "Avery Brundage Collection". Identifiers are not zero-padded ("8/7/1", "15/11/810", "12/9/51").
  - ala: Record Groups, e.g. "Information about, but not by, ALA" and "Office for Intellectual Freedom (OIF)". Identifiers look like "19/1/6", "85/53/3", "9/6/6".
  - rbml: only 2 groups, "01" Archival Collections and "02" Print Material Collections.
  - ihlc: none. The page says "No materials are classified under" followed by nothing.
  - Names contain entities: "Chancellor&#039;s Office", "Admissions &amp; Records".

## B. Collection records (controlcard / findingaid header)

I sampled 21 control cards (5 ihlc, 4 libweb, 3 sousa2, 5 rbml, 4 ala). Fields are listed as typically filled / sometimes filled / rare, with median length and maximum length in characters.

- **ihlc**
  - Always: Title (median 57, max 89), Collection identifier ("1033", "948", "781", zero-padded "001"), Extent ("1.0 item", "0.9 cubic feet", "205.9 cubic feet", "0.0 " with an empty unit), Subjects (median 142, max 798), Acquisition Note, Acquisition Source (215 / 555), Scope and Contents (344 / 1507).
  - Usually: Abstract (141 / 368), Creators, Forms of Material, Collection Material Type ("Personal Papers", "Official Records", "Publications"), Biographical/Historical Context (1268 / 1346).
  - Rare: Languages, "More Extent Information" ("14 oversize folders, 5 items"), Related Materials, Related Publications.
  - The Acquisition Note is rendered as "Source: The Library acquired the collection in 2015.." — the template appends a period, giving a double period.
- **archives (illinois set, libweb and sousa2)**
  - Always: Title (median 44–60), Series Number, Acquired, Volume ("0.1 cubic feet", "17.4 cubic feet", "239.5 cubic feet", "23.55 cubic feet"), Arrangement (31–1051), Creator(s), Subjects (266 / 2365), Locations, Description (500–1800).
  - Often: Administrative History of Creating Unit (about 2,255) or Biographical Note (3070 / 3436), Detailed Description (series list), Online Images / Records.
  - Sometimes: Processing Note (587), Related Materials ("see also RS 35/3/68"), Special Formats, Special Languages ("Japanese / German"), Acquisition Note ("Donation."), Unprocessed Materials (links to accessions).
- **rbml**
  - Always: Title (median 28), ID, Extent ("207.0 Linear Feet", "31.5 Cubic Feet", "3.0 Box(es)", "1.0 Item(s)", "1.0 Pamphlet Binder"), Languages, Restrictions ("Open to researchers."), Rights (boilerplate of about 900 characters).
  - Often: Collection Material Type, Scope and Contents (1599 / 2011).
  - Sometimes: Abstract, Creators, Subjects, Acquired ("1954."), Related Materials, Other Note, Descriptive note.
- **ala**
  - Always: Title (up to 81), Series Number, Acquired, Volume ("2.6 Cubic Feet"), Arrangement, Subjects, Locations, Description (589 / 1227).
  - Often: Creator(s) and Languages (3/4). Sometimes: Administrative History (2/4, about 1,720) and Predominant Dates (1/4, "1994-2019").
- **Date strings** (titles and dates):
  - Ranges and lists: "1857-1953", "1908-82", "1991-93", "1903-05, 1907-68", "1900-13, 1921-22, 1926-57", "1983-84, 1994-2019", "1975-1976, 1981, 1983, 1986, 1990-1999, 2001-2003", "1984-90, 1993-95, 1998, 2007, 2009-2010".
  - Open-ended: "1976-", "2022 -".
  - Approximate: "circa 1880-1932", "ca. 1930-2023", "circa 1601-1674", "1816-circa 1942", "1985-ca. 1986, 1988-89, ca. 1993", "ca. 1924-1928, and 1977", "circa 1940s", "1890s?-1996", "1900s".
  - Other forms: "undated", "1842-undated", "June 12, 1864", "April 1919", "March 20, 1930". Some collections have no date at all (ihlc 7146 EAD has no `<unitdate>`).
- **"Acquired" (AcquisitionDate plus free text)** — not normalised:
  - Invalid or partial dates: "00/00/1932.", "05/00/2020.".
  - Short forms: "9/1963", "9/76", "1954.".
  - Multiple dates: "4/17/64; 7/10/75; 3/90; 10/91; 11/95", "10/9/1968; 11/1/1972", "09/13/2012. 4/9/2018", "02/05/1975. 3/5/75; 10/13/76; 5/14/2003; 6/2004".
  - Date plus long free text: "03/28/2008. Additions accquired January 24, 2012; June 19 and 26, …" (181 characters, typo in the source).
- **Extent and units** (EAD exports the extent as "2.00" / "17.40", and the unit as `type="Cubic Feet"` or `type="items"`):
  - Units: cubic feet / Cubic Feet, Linear Feet, items / Item(s), Box(es), folder / Folder(s), Pamphlet Binder, Letters.
  - Alternate extent statements: "1 microfilm reel", "(87 items)", "14 oversize folders, 5 items".
- **Other URL / PDF finding aids.** Box/folder lists are hosted at `https://files.archon.library.illinois.edu/{ihlcsfa|uasfa|alasfa}/<name>.pdf`, e.g. `ihlcsfa/wood.pdf`, `uasfa/2620016.pdf`, `alasfa/1901006a.pdf`. The ALA/UA names are the RS number zero-padded to 2/2/3 digits plus a letter.
- **Finding-aid authors and processing:**
  - Authors: "Unknown; Jessica Williams; Salvatore V. De Sando."
  - Processing note (about 590 characters) about renumbered boxes.
  - EAD note: "Other Information: 30 Pages".
  - Accession 738 "Avery Brundage Additions" (Received Extent 1.0 cubic feet, Acquired 03/07/2007) is publicly visible. So is a test record: "Sam an' Ella Papers-test, 1971-89" (accession 798).
- **Location and Aeon tables.** Large collections render 219–876 location rows: building, then a box or a range ("Box 001-010", "sb108", "Volume 361", "Steamer Trunk 2", "Other").
  - Page sizes: rbml controlcard 10 is 301 KB; sousa2 3132 is 92 KB.
  - The ihlc Aeon table has malformed nesting: `<td>..<td>..</td></td>`.

## C. Finding-aid content hierarchy

| collection | items | depth | levels (count) | notes |
|---|---|---|---|---|
| libweb 4719 Avery Brundage (1.5 MB HTML; 0.9 s; 213 queries) | 9,312 | 1–6 (depth histogram 43 / 486 / 5633 / 2635 / 504 / 11) | Folder 5734, Item 2577, Box 534, Sub-Series 362, Reel 57, Series 43 (plus 1 restricted series), Volume 4, "Oversize Folder OS-…" | Physical Box sits between intellectual levels (Series → Box → Sub-Series → Folder, repeated per box). 2,694 item notes, mostly "folder 1 of 2"; some are raw `<p>`+tab HTML, `&nbsp; `, or "Tracklist:" blocks. Series 43 shows as "[information restricted]" in both navs. A dl.gif digital-content icon links to digitalcontent&id=9064. |
| sousa2 3132 Sousa Music & Personal Papers (1.26 MB; 6.7 s) | 6,427 | 3 | Series → Box → Folder, plus "Oversize Portfolio Case", "Steamer Trunk", "Furniture Piece", "Oversize Panoramic P…" | 7,352 notes are user-field style ("Composer/Arranger: …", "Microfilm: Box 760, Reel 80, No. 97-1020", one typo "Microflim"). Titles look like "Folder 1: Dreams, ca. 1800s (FS, P)" and "[S23C25]". One unnumbered folder ("Folder: Grand March"). 32 items have digital links. |
| sousa2 11439 Sheynkman | 774 | 4 | Series → Sub-Series → Box → Folder | Russian romanised titles ("p'esy", "Val's"). Notes like "Moscow: …". No Cyrillic. |
| rbml 10 H.G. Wells papers (1.0 MB; 7.2 s) | 1,849 | 1–4 | Folder C- 503, Folder B- 491, Folder A- 180, Item 140, Box 136, File 84, Series 11, File KS-/JP-/OH-/TO-/…, "Folder Photostat B-" | Alphanumeric container identifiers ("File A-001", "File AD-001", "Folder C-580 W2-1"). 326 notes, mostly "Location: C14.03.05". One note starts with U+200B. |
| ihlc 777, ala 8814 | 0 | – | – | No content in the DB. The finding aid is only a "PDF Box/Folder List" (OtherURL). Common on ihlc and ala. |

- Partial views via `&rootcontentid=N` still return about 300 KB (the series navigation is repeated). Example: Brundage Series 39 Audiovisual has 53 items, depth 5, notes up to 618 characters, "Tracklist" notes, and levels Box → Sub-Series 1: Films → Item.
- Titles: median length about 45–48, p95 69–95, maximum 214 (Brundage: "Folder 1: Three IOC Vice-Presidetns Representing the Executive Board … January 5-7, 11-12, 13-14, 1971") and 123 on rbml ("File 17: Boon, the Mind of the Race, … (entire text)").

## D. Creators, subjects, classifications

- **Creator pages** show "Name: Brundage, Avery (1887-1975)", Historical Note, related creators "(Associate)", and the related collections, accessions and digital content. rbml creator 4 (H.G. Wells) links to 16 collections.
- **Creator date shapes:** "1860-1936", "1920-", "b. 1850", "fl. 1850", "ca. 1890-1950", "1850-1920?", "19th century", or none. The share with no dates varies: ihlc 75%, ala 58%, rbml 41%, libweb 27% (from sampled browse pages of 24–171 names).
- **Creator types and name shapes:**
  - Families: "Zinser Family", "Sullivant family", "Wells Family".
  - Corporate: "Zeta Beta Tau. Rho Chapter (University of Illinois)", "University of Illinois at Urbana-Champaign. Library", "Office and Professional Employees International Union, Local 28".
- **EAC** (`p=creators/eac&id=N&templateset=eac&disabletheme=1`): `entityType` is always `person`, even for a family (rbml 1240) or a corporate body (libweb 2074). This is an upstream bug: `case "Personal Name"|| "Unassigned" || "Name":` evaluates to `case true`.
  - Other elements: `existDates localType='lcnaf'`, `cpfRelation cpfRelationType='associative'`, and resourceRelation creatorOf.
  - It contains the text "Revision date is not currently tracked in the database."
  - xlink URLs use `http://` even though the site is served over https.
- **Subjects.**
  - 10 types, browsable via `subjecttypeid=1..10`: Corporate Name, Date, Family Name, Function, Genre/Form of Material, Geographic Name, Occupation, Personal Name, Title, Topical Term.
  - Hierarchy depth histograms (counted from " -- " in the displayed names, so literal " -- " inside a name also counts):
    - ihlc: 1753 / 2 / 0 / 1 (deepest is "African American women -- Illinois -- Springfield -- Societies and clubs").
    - archives: 5432 / 369 / 92 / 1. "Music" (id 3121) has only 2 real children ("Instruction and study", "United States"), yet 153 other subjects display as "Music -- …" (id 2371 "Music -- India", id 2372 "Music -- India -- Agriculture and Natural Resources"). Real hierarchy and literal " -- " in names cannot be told apart in list output.
    - rbml: 150 / 45 / 5. The hierarchy is misused for name parts: "Esher, Reginald Baliol Brett -- Viscount -- 1852-1930".
    - ala: 4483 / 51.
  - Three separator styles are mixed:
    - the hierarchy delimiter " -- ";
    - a literal "--" inside flat strings ("Champaign County--Champaign (Ill.)", "Adams County--Camp Point (Ill.)");
    - " - " on the library_web controlcard ("Argentina - Sports and Recreation").
  - Name lengths: median 17–26 characters, maximum 96.
  - `subjects&id=` behaviour: a leaf subject returns 302 to `core/search&subjectid=`; a parent shows "Sub-Terms Under X".
  - core/search results are grouped by classification ("Fine Arts and Applied Arts › University Bands", "( 19 Matches)"), then by digital content.
- **Classification numbering:** "26/20/37", "12/9/51", "15/11/810", "8/7/1" (archives); "19/1/6", "85/55/2" (ala); "01/MSS00071", "01/POST-1650 MS 0408", "01/PRE-1650 MS 0192", "02/Sutton", "02/Asian", "02/Zapf" (rbml). Search results show the identifier without the "RS" prefix; Aeon CallNumber uses "RS 26/20/37".

## E. Digital library

- **Fields:** Title, Date, Description (may contain `<p>`), Phys. Desc, ID, Repository, Found in (collection plus full content path), Creators, Subjects, Publisher ("unpublished"), Rights (boilerplate up to about 460 characters), Languages.
  - Phys. Desc examples: "TIFF / Original print is 24.8 x 19.9 cm", "PDF / Original pages are 33.8 x 26 cm", "Contains 60.4 MB of files…", "1.3 TB of files in varioius formats.".
  - Found in example: "Gwendolyn Brooks collection… › Series 4 › Sub-series 1 › Box 330 › Folder 15 › Item 2: …".
  - Some records show an "Access link:" ContentURL to `https://digital.library.illinois.edu/items/<uuid>` or `/collections/<uuid>/tree`.
- **ID shapes:** "0007068", "ALA0002919", "120951_85_02_RatcliffIntermezzo", "2017-1209051-001", "00086_04_01_01_15_003_002", "ebfaa750-6464-0137-6cef-02d0d7bfd6e4-a", "3103017a-nearline".
- **Three kinds of record:**
  1. With files: JPEG images ("(JPEG Image, 67.08 KB)") or PDFs ("(PDF File, 23.26 MB)", "(PDF File, 3.05 MB)").
  2. Metadata plus an external access link, with no file. This is most rbml and all audiovisual records seen.
  3. Born-digital or "nearline" descriptions with no file.
  - No mp3, mp4 or other audio/video files were seen hosted in Archon.
- **Files** (`getfile`):
  - `&preview=short`: a 100×80 gd-jpeg thumbnail of about 3.6 KB.
  - `&preview=long`: 400 px wide (400×237, 21 KB, gd-jpeg comment "CREATOR: gd-jpeg v1.0").
  - Original: streamed with no content-length.
  - Headers: `content-type: Image/jpeg` (capital I), `content-disposition: attachment; filename="<ID>.jpg"`, `content-transfer-encoding: binary`.
- Records with files show "Email us to request a hi-resolution copy" or "Request hi-res copy". Previews are public; no access-restricted file was encountered.
- **Thumbnails** (`digitallibrary/thumbnails&collectionid=4719`): 10 per page, paged with `thumbnailpage=N`, each image a `getfile&id=N&preview=short`.
  - Titles are truncated to 27 characters plus "..." ("Avery Brundage at the Openi...").
  - Identical titles are numbered in the data: "Avery Brundage (1)…(5)", "Wounded Men on the Transport Mercury (2)".

## F. Text and encoding quirks seen live (all pages are valid UTF-8, served `charset=UTF-8`)

- **Mojibake** (double encoding stored in the data):
  - "Marche Ã?cossaise (Part 1)" next to the correct "Marche Écossaise (Part 2)". The É became "Ã" plus a literal "?", because the 0x89 byte was lost.
  - "Walkure"Â\u00a0 (P)", "ArrangedÂ\u00a0 after" (sousa2).
  - "1960.Ã?Â\u00a0 Opening" (Brundage).
  - "Former Shelf Mark: "Alluvial 1 Deposit":Â 001-001" (rbml).
- **Literal "?" replacing a character:**
  - "Czarski, Wac?aw" (ł, rbml 10).
  - "Ship o? dreams" (an apostrophe, sousa2).
  - "Universit? de" was **not** found.
  - Legitimate "?" also appears: "1720?-1782", "1890s?-1996", and titles such as "Who Knows?".
- **Correct non-ASCII text:**
  - Accents and Latin Extended letters: é è ê á ä ö ü ñ ó É ō, "Iḷisaġvik College" (U+1E37, U+0121), "Rinshirō", "Guille-Allès", "Kodály", "Brün".
  - A **decomposed** accent: "López" with U+0301 (ala subjects).
  - Punctuation: curly quotes ’ “ ” (rbml titles; ihlc `28”`), … (32 on libweb), en dash –, ×, ©.
  - NBSP is very common (650–1,130 per instance, partly from the template).
  - Zero-width space U+200B at the start of a note (rbml).
  - **No non-Latin scripts** (Cyrillic, CJK and so on) were seen. Russian and Japanese material is romanised.
- **NBSP stored in data** renders as "&nbsp; ": "Animal&nbsp; Rights", "Olympic Games&nbsp; Bids".
- **Entities in output:** `&#039;`, `&quot;Lisa&quot;`, "Admissions &amp; Records". A backtick is used as an apostrophe ("Sousa`s University of Illinois Band Audition").
- **BBCode in data:**
  - Rendered on controlcards: `[u]Box 1[/u]` becomes `<span style="text-decoration:underline">`.
  - Left **raw** in list teasers and in MARC 520: `[i]Through the Years…[/i]` (ihlc), `[url=https://i-share-uiu.primo…]…[/url]`, `[i]Supersonic Saucer[/i]`, `[u]The World[/u]` (rbml), `[url=http://archives.library.illinois.edu/archon/index.php?p=collections/controlcard&id=2251]Allen Weller Papers (R.S. 12/1/20)[/url]` (a legacy-domain link, libweb MARC).
- **Raw HTML in data:** `<p>` with tabs in content notes and digital descriptions. No escaped `&lt;p&gt;` was seen.
- **Long fields:** biographical note 3,438 characters (Sousa), administrative history about 2,255, scope about 2,011, rbml rights 909, digital description 1,356.
- **Typos and data errors:**
  - Typos: "Gemany", "varioius", "accquired", "Microflim", "Vice-Presidetns".
  - Wrong teasers: rbml list shows Sturm/Yeats text under "Charlotte Mary Yonge letters" and Legge text under "William Sutton personal book collection". This is a **template bug**: `rbml/collectionlist.inc.php` never resets `$collTeaser`, so a collection with no Abstract or Scope inherits the previous item's teaser.

## G. Export formats (public GET)

| format | URL | result |
|---|---|---|
| EAD 2002 | `?p=collections/ead&id=N&templateset=ead&disabletheme=1` | 200 `text/xml; charset=UTF-8`, well-formed on libweb 4698, ihlc 7146, rbml 1218 and ala 8814. See details below. |
| EAC-CPF | `?p=creators/eac&id=N&templateset=eac&disabletheme=1` | 200 text/xml, has an `<?xml …?>` declaration, well-formed. entityType bug (see D). schemaLocation has trailing spaces. |
| MARC (display) | `?p=collections/marc&id=4698` (not linked in the UI) | 200 themed HTML showing LDR/003 IU-Ar/005/008/099/100/245/300/351/520/653. See details below. |
| kardex | `?p=collections/controlcard&id=4698&templateset=kardexcontrolcard&disabletheme=1` | 200, a 42 KB HTML fragment (no `<html>` wrapper) |
| print | `&templateset=print&disabletheme=1` on controlcard/findingaid | 200 (ihlc controlcard 777: 139 KB, unthemed) |
| EAD list | `?p=collections/eadlist` | **500, empty body**, on all 4 installs |
| RSS / OAI | – | none in the source or in any page |

- **EAD details:**
  - **No XML declaration.** The file starts with a newline; the ala install uses CRLF.
  - The `<creation>` text "encoding in EAD by Archon 3.21 … on October 22nd, 2026" changes on every request.
  - No `<dsc>` when the collection has no content.
  - `repositorycode` is "US-iuhs" on ihlc and "US-IU-Ar" on ala.
  - Other values: `unitdate normal="1983/2019"`, `type="bulk"`, `extent type="Cubic Feet"` with value "2.60".
  - `dao` links point to digital.library.illinois.edu.
- **MARC details:**
  - **005 uses a 12-hour clock**: `date('h')` gave "051949" for a request at 17:19.
  - 300 shows "_a17.40 _fcubic feet".
  - 653 local subjects use `_x` subdivisions.
  - BBCode is left raw in 520.

## H. Infrastructure, headers, errors

- **Server** (all installs): `Apache/2.4.6 (CentOS) OpenSSL/1.0.2k-fips mod_fcgid/2.3.9 PHP/5.4.16 mod_wsgi/3.4 Python/2.7.5`. `x-powered-by: PHP/5.4.16`. HTTP/2.
- **Caching:** `cache-control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0`, `pragma: no-cache`, `expires: Thu, 19 Nov 1981 08:52:00 GMT`.
- **Cookie:** `set-cookie: archon=<sid>; path=/ihlc/|/archives/|/rbml/|/ala/; secure`. There is **no HttpOnly and no SameSite**. Theme and repository are stored in this session, so libweb and sousa2 need separate cookie jars.
- **Footer on every page:** "Powered by Archon Version 3.21 rev-3", plus debug output "Page Generated in: 0.046 seconds (using 17 queries). Using 4.9MB of memory. (Peak of 4.97MB.)".
- **Slow pages:**
  - archives subjects view-all: **24.2 s, 12,577 queries**.
  - ala subjects view-all: 5.3 s, 9,194 queries.
  - Large finding aids: 6.7–7.2 s.
- **No PHP Warning, Notice, Deprecated, Fatal or Strict text** in any of the 193 cached responses (40 junk crawl-trap pages were discarded), so display_errors appears to be off. No Archon error divs were seen.
- **Bugs and odd behaviours:**
  - **Subjects pagination:** `subjects.php` calls `searchSubjects(q, NULL, typeid, LIMIT+1, offset)`, but the signature is `(q, ParentID, SubjectTypeID, ShowChildren, Limit, Offset)`. The effect is Limit=(N-1)×40 with Offset=0:
    - page=1 (also the "View All" page) uses Limit 0, so it lists ALL subjects. It then shows "Next" and drops the last row with `array_pop`.
    - page=2 lists the first 40, with no "Next".
    - page=N for N≥3 lists the first min(total, (N-1)×40) rows. It is never empty, so the past-last-page 302 never fires, and "Next" always shows, so crawlers and end-of-list probes never stop.
  - **Beyond-last page:** collections, creators and digital browse return 302 to `index.php?p=<route>&browse`. The redirect uses `header()` without `exit`, so the body is still rendered.
  - **Nonexistent IDs:** controlcard returns 200 with an empty main area.
  - **IE stylesheet path:** `themes/themes/<theme>/js/cluetip/jquery.cluetip.ie.css` (doubled directory).
  - **Cache-busting query** on stylesheets, set per theme: `?v=20260925` (library_web), `?version=20261013` (ihlc), `?v=20260812` (rbml), `?v=20240717` (ala_redo), `?v=20241220` (sousa2).

---------------------------------------------------------------------------------------------------------------------
## Fixture recommendations (synthetic; do not copy the live text)

1. **Topology.**
   - One DB with 3 repositories: repo 1 "University Archives" (illinois template set), repo 2 a music archive with its own theme (the sousa2 pattern), and repo 3 an RBML-like repository (rbml template set).
   - Also an ihlc-like install profile: no classifications, numeric identifiers, PDF-only finding aids.
   - Test `setrepositoryid=0` (all repos) and `=2` (filtered), and `settheme` persistence per session.
   - Give one repository a TemplateSet (it overrides print/kardex on controlcard) and one collection a TemplateSet (it overrides the finding aid unless `templateset=EAD`).
2. **Volume tiers.**
   - Small (unit tests): 30 collections, 25 creators, 60 subjects, 20 digital content records.
   - Realistic (integration): 300–400 collections (at least 11 pages at 40 per page), 300 creators, 2,000 subjects, 400 digital content records.
   - Stress (opt-in): 7,300 collections and 6,000 subjects (reproduces the 12k-query view-all), plus one 9,300-item finding aid.
   - Include letters with no entries ("#", Q, X, Z) and a letter with more than 40 entries.
3. **Classifications.**
   - 3-level tree: about 20 top-level units with gaps in the numbering (no 42/47), level 2 with 3–10 children, level 3 with leaf collections.
   - Identifiers both unpadded "26/20/37" and padded "01/MSS00071".
   - One alphanumeric non-path ID "01/POST-1650 MS 0408", and names with `'` and `&`.
4. **Collections** — mix the fields as in B:
   - Always: title and dates. About 90% have extent with a unit.
   - Scope 60–100% (300–2,000 characters). Bio/admin history 30–60% (1,200–3,500 characters).
   - Arrangement on the illinois set (30–1,050 characters).
   - Abstract mostly ihlc/rbml-style (140–370 characters).
   - Rare: languages (often English only, plus some "Japanese, German"), Acquisition source/notes, Processing note, Related materials, Other note, Finding-aid author list with ";", OtherURL to a PDF.
   - Edge cases:
     - Extent 0.0 with no unit, extent 239.5, 23.55. AltExtentStatement "(87 items)" with nested parentheses.
     - AcquisitionDate values "00/00/1932", "05/00/2020", 2-digit years "9/76", multiple ";"-separated dates, date plus 180-character free text.
     - Every date-string shape listed in B: "1976-", "1842-undated", "1890s?-1996", "circa 1601-1674", "ca. 1924-1928, and 1977", empty dates.
     - Normal dates such as `1983/2019` for EAD.
     - One disabled collection, one "-test" record, one collection with **no Abstract and no Scope that follows one with a Scope** (catches the stale-teaser bug).
5. **Content (finding aids).**
   - (a) 0 items with only an OtherURL PDF.
   - (b) about 50 items, depth 3 (Series → Box → Folder).
   - (c) about 800 items, depth 4 (Series → Sub-Series → Box → Folder).
   - (d) about 1,850 items, depth 4, with alphanumeric LevelContainerIdentifiers ("A-001", "C-580 W2-1", "KS-3") and level "File".
   - (e) about 6,500 items, depth 3, with odd containers ("Steamer Trunk", "Oversize Portfolio Case", "Furniture Piece") and user fields on most items ("Composer/Arranger:", "Microfilm:", "Location: C14.03.05").
   - (f) about 9,300 items, depth 6, with Box between intellectual levels, Reel/Volume/"Oversize Folder OS-1", 44 series, one series **restricted** (must render "[information restricted]"), about 30% of items with notes (short "folder 1 of 2", HTML `<p>` notes, 600-character tracklists), unnumbered items, and items linked to digital content.
6. **Creators.**
   - Mix: about 75% personal, 20% corporate (dotted hierarchies "X. Y Chapter (University of …)"), 5% family. About 50% have no dates; date shapes as in D.
   - About 30% have a bio. Associative relationships.
   - One creator linked to 15 or more collections and to accessions and digital content.
   - Golden EAC files for family and corporate creators: they will expose the entityType bug.
7. **Subjects.**
   - All 10 types. Hierarchy: about 92% depth 1, 6% depth 2, 1.5% depth 3, a few at depth 4.
   - One parent with 2 real children plus more than 150 flat subjects whose names start with "Parent -- …", so real hierarchy and literal " -- " are mixed.
   - Literal "--" inside flat names, NBSP and decomposed accents in names, an 80–100 character corporate name.
   - Subject leaf → search redirect.
8. **Digital content.**
   - About 40% JPEG with preview short (100×80) and long (400 px).
   - About 15% PDF: use tiny real PDFs but set realistic metadata sizes (3–25 MB) to exercise "(PDF File, 23.26 MB)" formatting.
   - About 35% metadata-only with ContentURL. About 10% born-digital with no file.
   - IDs in all the shapes listed in E. Content links to a deep content item (depth 5 path).
   - At least 11 items in one collection so thumbnails need 2 pages.
9. **Text corpus for encoding tests** — embed in titles, notes and subjects:
   - Mojibake "Ã©", "Ã?", "Â\u00a0"; literal "?" for ł/’.
   - NBSP, U+200B, U+0301 combining accent, curly quotes, …, –, ×, ©, backtick apostrophe.
   - `&#039;`, `&amp;`, `"` inside attributes.
   - BBCode `[b] [i] [u] [url=…]…[/url]` (including a URL containing `&`), raw `<p>`/`<br>`/`<a href>`.
   - Long fields (3.5k), 2-digit years.
   - No live non-Latin scripts were observed, but add a few Cyrillic and CJK strings anyway as forward-looking coverage.
10. **Export golden files.** EAD, EAC, MARC, kardex and print for the fixtures above. Freeze time: EAD creation date and MARC 005 are time-dependent, and 005 uses the 12-hour `h`. Assert well-formedness, the absence of an XML declaration in EAD, CRLF tolerance, and that eadlist currently returns 500.

## Unknowns that only the DB/infra can answer

- **Configuration:** CONFIG_CORE_DEFAULT_TEMPLATE_SET and default theme per install, pagination limit (40 inferred), EscapeXML and other Configuration Manager values, enabled packages per install.
- **Database:** MySQL/MariaDB version and table/column charset and collation (utf8 vs latin1). Whether the mojibake and "?" are stored bytes or created at render or import time. Whether rbml shares a DB with any other install (ihlc, ala and archives are shown to be separate).
- **Per-record template sets:** whether any live Repository.TemplateSet or Collection.TemplateSet values are set (the repo code honours both).
- **Hidden records:** disabled or unpublished collections, staff-only fields (ccardstaff), restricted content counts, accessions not linked publicly, research carts and appointments, users, sessions, Aeon/armarium integration.
- **Real totals:** collection content rows (archives content IDs reach about 229k), user fields, location entries, digital files (DB blob vs filesystem, total size, MIME types, whether any audio/video is hosted), file-type table.
- **PHP runtime:** error_reporting/display_errors, timezone (MARC 005 suggests 12-hour formatting with UTC or local time), memory_limit and max_execution_time (subjects view-all takes 24 s), opcode cache.
- **Live code:** where the customised templates come from (Aeon, location filter, PDF FA links are not in this repo); the cron jobs or tooling that produce `files.archon.library.illinois.edu` PDFs; the cause of the eadlist 500 (PHP error log).
- **Search:** full-text search behaviour (`core/search&q=`, `f=searchmultiple`, pdfsearch) was not exercised, because these are forms or `f=` routes.
