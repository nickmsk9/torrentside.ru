ALTER TABLE `torrents`
  ADD FULLTEXT KEY `ft_ai_search` (`name`,`search_text`,`descr`,`tags`);
