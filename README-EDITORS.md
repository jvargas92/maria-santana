# Editing this site

A guide for keeping the site up to date — adding videos and articles, changing
the pages, and uploading images. You don't need to know anything technical, and
nothing here can break the site permanently. Where something *is* risky, it
says so.

If you get stuck, stop and ask rather than guessing. Nothing is urgent enough
to risk the live site.

Two reassurances before you start. Every save keeps the previous version, so a
change can be undone. And you cannot delete a video or an article at all — the
worst you can do is take one off the site, which is reversible.

---

## Signing in

Go to **/user/login** and sign in with either your email address or your
username — both work.

The whole site is private at the moment, so you'll be asked to sign in before
you can see any page, even the home page. That's deliberate.

Once you're in, a **Navigation** sidebar appears on the left. Everything in
this guide starts from there.

---

## The one idea worth understanding first

There are two different things you can edit, and mixing them up is the main
source of confusion:

| | What it is | Where you edit it |
|---|---|---|
| **Content** | One video, or one article | **Content** in the sidebar |
| **Pages** | The arrangement of a page — hero, headings, buttons | **Content → Pages** |

Here's the part that catches people out: **the lists on the home page fill
themselves in.** The "Latest videos" and "Articles & commentary" sections pull
in your newest videos and articles automatically. You do *not* edit the home
page to change what appears there — you add or edit a video or an article, and
the home page updates itself.

The same is true of the Videos page, the Articles page and the Socials page.

---

## Adding a video

**Content → Add content → Video**

| Field | What it's for |
|---|---|
| **Title** | Required. The headline shown on the card and the page. |
| **Series** | A small label above the title, e.g. a programme name. Optional. |
| **Featured image** | The picture on the card. See *Adding images* below. |
| **Description** | One or two sentences. This is what shows on the card, so make it read well on its own. |
| **Content** | The main body of the page. |
| **Source** | Where it came from — CNN, Instagram, YouTube and so on. |
| **Source URL** | The address of the original. See the note below. |
| **Sticky at top of lists** | Tick this to pin the video first on the home page. |
| **Quote text**, **Name**, **Position** | An optional pull quote and who said it. |
| **Aired** | When it went out. Free text. |

When you're done, set the state at the bottom to **Published** and press
**Save**. Leaving it as *Draft* saves your work without putting it on the site.

### About Source URL

This field does more than record where something came from.

- Paste an **Instagram** or **Threads** link and the post is embedded on the
  page, after the visitor accepts cookies.
- Paste a **Substack**, **CNN** or **YouTube** link and it becomes a tidy
  "Read this on…" link instead.

One limitation worth knowing: a vertical Instagram video will not fill the
frame. Instagram crops its own embeds to a squarer shape and there's nothing
the site can do about it. For a vertical video that should play properly, use
the YouTube version of it instead.

---

## Adding an article

**Content → Add content → Article**

| Field | What it's for |
|---|---|
| **Title** | Required. |
| **Author** | Who wrote it. |
| **Featured image** | **Required.** The article can't be saved without one. |
| **Description** | The short summary shown on cards. |
| **Content** | The body of the article. |
| **Source** / **Source URL** | As for videos, above. |
| **Publish on** / **Unpublish on** | Optional. Set a date and the site will publish or hide the article by itself. |

### Articles that appear on their own

**Substack posts arrive automatically, once an hour.** You never need to copy a
Substack post across by hand — if you do, you'll end up with two of them.

If you edit one of these imported articles, be aware: should that post later be
edited on Substack, the import will replace your changes with the Substack
version. So **make the correction on Substack**, not here, and it will flow
through within the hour.

---

## Changing the home page

**Content → Pages → Home**, then edit it.

The page opens in a visual editor. Click any piece to change it, and drag to
reorder. You can safely change:

- the hero image, heading and buttons at the top
- any section heading, such as "Latest videos"
- button labels and where they point
- the colour behind a section

What you **cannot** change here are the lists of videos and articles
themselves. They're filled in from your content, as described above. To change
what appears:

- **to feature a particular video first** — edit that video and tick *Sticky at
  top of lists*
- **to change what's listed** — add, edit or unpublish the videos and articles
  themselves

The home page shows the **2 newest videos** and the **3 newest articles**, so
anything older moves off it naturally.

---

## The Videos and Articles pages

**Content → Pages → Videos** (or **Articles**)

These work the same way as the home page: the headings, intro text and buttons
are editable, and the lists fill themselves from your content.

---

## The Socials page

**Content → Pages → Socials** holds the heading and intro text.

The list of platforms itself comes from one place: **Structure → Menus → Social
links**, at `/admin/structure/menu/manage/social`. Editing it there updates both
the Socials page *and* the row of icons in the site header, so you only ever
maintain it once.

For each platform you can set:

| | |
|---|---|
| **Menu link title** | The platform's name, as shown on the page. |
| **Link** | The address of the profile. |
| **Description** | A sentence about that platform. This appears under the name on the Socials page. |
| **Icon** | The logo. Choose one by typing the platform's name. |
| **Enabled** | Untick to hide a platform without deleting it. |
| **Weight** | Controls the order. Lower numbers come first. |

Two helpful behaviours:

- A platform with **no address yet** is skipped automatically, so you can set
  one up before the account exists without a broken row appearing.
- Untick **Enabled** to take a platform down temporarily.

One thing to know: there's no Substack logo available, so Substack uses a
bookmark symbol as the nearest match. That's intentional.

---

## Adding images and other media

**Content → Media → Add media**, then choose the type: Image, Document, Video,
Remote video or SVG Image.

### Images

Accepted formats are **jpg, jpeg, png, gif and webp**.

**Alt text is required.** This is a short description of what the picture shows,
read aloud to people using screen readers and shown if the image fails to load.
Describe the picture, don't repeat the headline:

- Good: *Maria Santana interviewing a guest on set*
- Not useful: *image*, *photo1.jpg*, or the article's title again

After uploading you can drag the crosshair on the image to set its focal point
— the part that stays in view when the image is cropped to fit a card.

### Uploading several at once

**Content → Media → Bulk upload** lets you add many files in one go. You'll
still need to give each one alt text afterwards.

### Reusing what's already there

When a field asks for an image, you can pick something already uploaded instead
of adding it again. Prefer this — it keeps the library manageable.

### Remote video

The **Remote video** type accepts **YouTube and Vimeo** links only. Other
platforms won't work here; use the *Source URL* field on a video instead.

### One real warning

**Deleting media is permanent.** Media is the one thing you can delete and the
one thing that cannot be brought back — and anything using that image will be
left without one. If you are not certain, leave it alone: an unused image in the
library costs nothing.

---

## Publishing, scheduling and unpublishing

Every video and article is either a **Draft** (saved, not on the site) or
**Published** (live). The setting is at the bottom of the edit form.

- To take something down, set it to **Unpublished** and save. It stays in the
  system and can be put back.
- To publish or hide something on a set date, use **Publish on** and
  **Unpublish on**.
- Your work is saved as you type, so a closed tab or a dropped connection
  shouldn't lose anything. You still need to press **Save** to commit it.

---

## If you make a mistake

**You want a video or an article taken off the site.** You can't delete them,
on purpose — set the state to **Unpublished** and save instead. That removes it
from the site while keeping it, which is almost always what's wanted. If
something genuinely needs deleting, ask.

**You want an earlier version back.** Open the item and use the **Revisions**
tab. Every save keeps the previous version, so you can compare and revert.

**You changed a page layout and want it back.** The page editor keeps its own
history. If you can't find your way back, stop and ask rather than rebuilding
it from memory.

**You deleted an image.** This one can't be undone. Re-upload it and reattach
it wherever it was used.

---

## Things to ask a developer for

These aren't things you've done wrong — they just can't be done from the admin
screens:

- adding a new kind of content, or a new field on videos or articles
- changing the structure of a page beyond the pieces already available
- changing how the Substack import behaves
- making the site public, or changing who can sign in
- anything involving the site's code, or a change you want on the live site that
  you've only made here
