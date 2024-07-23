## General
AzusaMute is a Pocketmine plug-in that works to mute players on the server, either one player or all players

## Features
- Can mute one player for a certain duration
- Can free the player's punishment from mute
- Can mute all players on the server
- Can free the mute for all players on the server
- Can check the player's mute status
- Custom message in config

## Command
Commands | Default | Permission
--- | --- | ---
`/mute` | Op | azusamute.command.mute
`/unmute` | Op | azusamute.command.unmute
`/muteall` | Op | azusamute.command.muteall
`/unmuteall` | Op | azusamute.command.unmuteall
`/mutecheck` | Op | azusamute.command.mutecheck


## Configuration
```yaml
# AzusaMute Configuration

# Enable/disable the op function on mute
# If "true", the player op will not be affected by the mute function.
# If "false", the player op will be affected by the mute function.
allow-op: false

# Message on the server
messages:
  already_muted: "§cThe player is already on mute"
  not_muted: "§cThe player is not on mute"
  unmute_success: "You are free from the §emute penalty"
  already_muted_all: "§cThe server is already on mute"
  not_muted_all: "§cThe server is not in mute state"

# Message when player is mute by admin/player op
  player_mute: "You have been mute for §c{time} §fby §b{punisher}"

# Message when successfully checking mute player
  mute_check: "§e{player} §fwas mute within §c{time} §fby §b{punisher}"

# Message when the player tries to send a message on mute
  mute_all: "§cYou cannot send messages because the server is on mute"
  player_muted: "You cannot send messages because you are on mute for §c{time}"

# Message when a mute player is unmuted
  mute_ended: "You are free from the §emute penalty"
  unmute_success: "You are free from the §emute penalty"

# Message if allow-op = true, then player op cannot be muted
  op_mute: "§cOP players cannot be muted"

# Broadcast on the server
  broadcast_mute: "§e{player} §fgets a mute penalty for §c{time}"
  broadcast_mute_all: "§cAll players on the server are mute"
  broadcast_unmute_all: "§eAll players on the server are free from mute"
```
