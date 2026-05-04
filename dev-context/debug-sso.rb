# Turn on verbose SSO logging and print the most recent error-level Logster log
# entries (these are how Discourse surfaces 500-level exceptions to admins).
SiteSetting.verbose_discourse_connect_logging = true
puts "verbose_discourse_connect_logging = #{SiteSetting.verbose_discourse_connect_logging}"

if defined?(Logster)
  store = Logster.store
  if store.respond_to?(:latest)
    msgs = store.latest(limit: 30, severity: [Logger::ERROR, Logger::FATAL, Logger::WARN])
    msgs.each do |m|
      ts = m.respond_to?(:timestamp) ? Time.at(m.timestamp / 1000.0) : "?"
      puts "--- #{ts} sev=#{m.severity rescue '?'} ---"
      puts m.message.to_s[0, 1500]
      puts "BACKTRACE:"
      puts (m.backtrace.to_s rescue '')[0, 2000]
    end
  end
end
