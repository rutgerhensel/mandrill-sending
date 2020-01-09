<?php die();?>

CREATE TABLE `scheduled_emails` (
  `id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `attempted_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `esp` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `template_slug` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `subject` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `recipients_json` text COLLATE utf8_unicode_ci NOT NULL,
  `payload_json` mediumtext CHARACTER SET latin1 NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `scheduled_emails`
--
ALTER TABLE `scheduled_emails`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `scheduled_emails`
--
ALTER TABLE `scheduled_emails`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;