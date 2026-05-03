names = SiteSetting.all_settings.map { |s| s[:setting].to_s }
relevant = names.grep(/connect|sso|override|auth_/i).sort.uniq
puts relevant
